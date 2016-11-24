<?php

namespace tpay;

/**
 * CardAPI class.
 *
 * See cards_instructions.pdf for more details
 */
class CardAPI
{
    /**
     * Tpay payment url.
     *
     * @var string
     */
    private $apiURL = 'https://secure.tpay.com/api/cards/';

    /**
     * Card api key.
     *
     * @var string
     */
    private $apiKey;

    /**
     * Card api pass.
     *
     * @var string
     */
    private $apiPass;

    /**
     * Api verification code.
     *
     * @var string
     */
    private $verificationCode;

    /**
     * The same as chosen in merchant panel (https://secure.tpay.com/panel)
     * In card api tab preferences.
     *
     * @var string
     */
    private $hashAlg;

    /**
     * PaymentCardAPI class constructor.
     *
     * @param string $cardApiKey       api key
     * @param string $cardApiPassword  api password
     * @param string $verificationCode verification code
     * @param string $hashAlg          hash algorithm
     *
     * @throws TException
     */
    public function __construct($cardApiKey, $cardApiPassword, $verificationCode = '', $hashAlg = 'sha1')
    {
        Validate::validateCardApiKey($cardApiKey);
        Validate::validateCardApiPassword($cardApiPassword);
        Validate::validateCardHashAlg($hashAlg);
        if ($verificationCode !== '') {
            Validate::validateCardCode($verificationCode);
        }

        $this->apiKey = $cardApiKey;
        $this->apiPass = $cardApiPassword;
        $this->hashAlg = $hashAlg;
        $this->verificationCode = $verificationCode;

        Util::loadClass('curl');
    }

    /**
     * Method used to sale initialization in Tpay system.
     * Successful request returns sale_auth used to redirect client to transaction panel.
     *
     * @param string      $clientName      client name
     * @param string      $clientEmail     client email
     * @param string      $saleDescription sale description
     * @param float       $amount          amount
     * @param string      $currency        currency
     * @param string|null $orderID         order id
     * @param bool        $onetimer
     * @param string      $lang
     *
     * @return bool|mixed
     */
    public function registerSale(
        $clientName,
        $clientEmail,
        $saleDescription,
        $amount,
        $currency = '985',
        $orderID = null,
        $onetimer = true,
        $lang = 'pl'
    ) {
        return $this->registerSaleBase(
            $clientName,
            $clientEmail,
            $saleDescription,
            $amount,
            $currency,
            $orderID,
            $onetimer,
            false,
            null,
            $lang
        );
    }

    /**
     * This method allows Merchant to host payment form on his website and perform sale without any client redirection
     * to Tpay.com system. This approach requires special security considerations.
     * We support secure communication by encrypting card data (card number, validity date and cvv/cvs number)
     * on client side (javascript) with Merchant's public RSA key and send it as one parameter (card) to our API gate.
     * A valid SSL certificate on domain is required.
     *
     * @param string      $clientName      client name
     * @param string      $clientEmail     client email
     * @param string      $saleDescription sale description
     * @param float       $amount          amount
     * @param string      $carddata        encrypted credit card data
     * @param string      $curr            currency
     * @param string|null $orderID         order id
     * @param bool        $onetimer
     * @param string      $lang
     *
     * @return bool|mixed
     *
     * @throws TException
     */
    public function directSale(
        $clientName,
        $clientEmail,
        $saleDescription,
        $amount,
        $carddata,
        $curr = '985',
        $orderID = null,
        $onetimer = true,
        $lang = 'pl'
    ) {
        if (!is_string($carddata) || strlen($carddata) === 0) {
            throw new TException('Card data are not set');
        }

        return $this->registerSaleBase(
            $clientName,
            $clientEmail,
            $saleDescription,
            $amount,
            $curr,
            $orderID,
            $onetimer,
            true,
            $carddata,
            $lang
        );
    }

    /**
     * Method used to create new sale for payment on demand.
     * It can be called after receiving notification with cli_auth (see communication schema in register_sale method).
     * It cannot be used if onetimer option was sent in register_sale or client has unregistered (by link in email or by API).
     *
     * @param string $clientAuthCode  client auth code
     * @param string $saleDescription sale description
     * @param float  $amount          amount
     * @param string $currency        currency
     * @param null   $orderID         order id
     * @param string $lang            language
     *
     * @return bool|mixed
     *
     * @throws TException
     */
    public function presale($clientAuthCode, $saleDescription, $amount, $currency = '985', $orderID = null, $lang = 'pl')
    {
        $params = $this->saleValidateAndPrepareParams($clientAuthCode, $saleDescription, $amount, $currency, $orderID, $lang, 'presale');

        Util::log('Presale params', print_r($params, true).' hash alg '.$this->hashAlg);

        $amount = number_format($amount, 2, '.', '');

        $params['sign'] = hash($this->hashAlg, 'presale'.$clientAuthCode.$saleDescription.$amount.$currency.$orderID.$lang.$this->verificationCode);
        $params['api_password'] = $this->apiPass;

        Util::log('Pre sale params with hash ', print_r($params, true).'req url '.$this->apiURL.$this->apiKey);

        $response = $this->postRequest($this->apiURL.$this->apiKey, $params);

        return $response;
    }
    /**
     * Make sale by client auth code.
     *
     * @param string      $clientAuthCode  client auth code
     * @param string      $saleDescription sale description
     * @param float       $amount          amount
     * @param string      $currency        currency
     * @param string|null $orderID         order id
     * @param string      $lang            language
     *
     * @return bool|mixed
     *
     * @throws TException
     */
    public function completeSale(
        $clientAuthCode,
        $saleDescription,
        $amount,
        $currency = '985',
        $orderID = null,
        $lang = 'pl'
    ) {
        $params = $this->saleValidateAndPrepareParams($clientAuthCode, $saleDescription, $amount, $currency, $orderID, $lang, 'presale');
        $response = $this->postRequest($this->apiURL.$this->apiKey, $params);

        if ($response['result']) {
            $saleAuthCode = $response['sale_auth'];
            $saleResponse = $this->sale($clientAuthCode, $saleAuthCode);

            return $saleResponse;
        }

        return $response;
    }

    /**
     * Method used to execute created sale with presale method. Sale defined with sale_auth can be executed only once.
     * If the method is called second time with the same parameters, system returns sale actual status - in parameter
     * status - done for correct payment and declined for rejected payment.
     * In that case, client card is not charged the second time.
     *
     * @param string $clientAuthCode client auth code
     * @param string $saleAuthCode   sale auth code
     *
     * @return bool|mixed
     */
    public function sale($clientAuthCode, $saleAuthCode)
    {
        if (strlen($clientAuthCode) != 40) {
            return false;
        }
        if (strlen($saleAuthCode) != 40) {
            return false;
        }

        $params = array(
            'method' => 'sale',
            'cli_auth' => $clientAuthCode,
            'sale_auth' => $saleAuthCode,
        );
        $params['sign'] = hash($this->hashAlg, 'sale'.$clientAuthCode.$saleAuthCode.$this->verificationCode);
        $params['api_password'] = $this->apiPass;

        $response = $this->postRequest($this->apiURL.$this->apiKey, $params);

        return $response;
    }

    /**
     * Method used to transfer money back to the client.
     * The refund can reference to chosen sale (sale_auth) or directly to client (cli_auth).
     * In both cases amount is adjustable in parameter amount.
     * If only cli_auth is sent amount parameter is required, if sale_auth is passed amount and currency is not necessary -
     * system will take default values from the specified sale. With sale_auth refund can be made only once.
     *
     * @param string      $clientAuthCode client auth code
     * @param string|bool $saleAuthCode   sale auth code
     * @param string      $refundDesc     refund description
     * @param float|null  $amount         amount
     * @param string      $currency       currency
     * @param string      $lang
     *
     * @return bool|mixed
     *
     * @throws TException
     */
    public function refund($clientAuthCode, $saleAuthCode, $refundDesc, $amount = null, $currency = '985', $lang = 'pl')
    {
        $errors = array();
        /*
         * 	required clientAuthCode or sale_auth, refund_desc and amount if only clientAuthCode passed
         */
        if (!is_string($clientAuthCode) || strlen($clientAuthCode) === 0) {
            $errors[] = 'Client auth code is empty.';
        } else {
            if (strlen($clientAuthCode) !== 40) {
                $errors[] = 'Client auth code is invalid.';
            }
        }

        if (!is_string($saleAuthCode) || strlen($saleAuthCode) === 0) {
            $errors[] = 'Sale auth code is empty.';
        } else {
            if (strlen($saleAuthCode) !== 40) {
                $errors[] = 'Sale auth code is invalid.';
            }
        }

        if (!is_string($refundDesc) || strlen($refundDesc) === 0) {
            $errors[] = 'Refund desc is empty.';
        } else {
            if (strlen($refundDesc) > 128) {
                $errors[] = 'Refund desc is too long. Max 128 characters.';
            }
        }

        if ($amount != null) {
            $amount = number_format(str_replace(array(',', ' '), array('.', ''), $amount), 2, '.', '');
        } else {
            if ($clientAuthCode && !$saleAuthCode) {
                $errors[] = 'Sale auth is false.';
            }
        }

        if (!isset($clientAuthCode) && !isset($saleAuthCode)) {
            $errors[] = 'Cli auth is not set and sale auth is not set.';
        }

        if (!is_int($currency) && strlen($currency) != 3) {
            $errors[] = 'Currency is invalid.';
        }

        if (sizeof($errors) > 0) {
            throw new TException(sprintf('%s', implode(' ', $errors)));
        }

        $params['method'] = 'refund';
        $params['desc'] = $refundDesc;

        if ($clientAuthCode) {
            $params['cli_auth'] = $clientAuthCode;
        }
        if ($saleAuthCode) {
            $params['sale_auth'] = $saleAuthCode;
        }
        if ($amount) {
            $params['amount'] = $amount;
        }
        if ($currency) {
            $params['currency'] = $currency;
        }
        if ($lang) {
            $params['language'] = $lang;
        }

        $params['sign'] = hash($this->hashAlg, implode('', $params).$this->verificationCode);
        $params['api_password'] = $this->apiPass;

        $response = $this->postRequest($this->apiURL.$this->apiKey, $params);

        return $response;
    }

    /**
     * Method used to deregister client card data from system.
     * Client can also do it himself from link in email after payment - if onetimer was not set - in that case system
     * will sent notification. After successful deregistration Merchant can no more charge client's card.
     *
     * @param string $clientAuthCode client auth code
     *
     * @return bool|mixed
     *
     * @throws TException
     */
    public function deregisterClient($clientAuthCode)
    {
        $errors = array();

        if (!is_string($clientAuthCode) || strlen($clientAuthCode) === 0) {
            $errors[] = 'Client auth code is empty.';
        } else {
            if (strlen($clientAuthCode) !== 40) {
                $errors[] = 'Client auth code is invalid.';
            }
        }

        if (sizeof($errors) > 0) {
            throw new TException(sprintf('%s', implode(' ', $errors)));
        }

        $params['method'] = 'deregister';
        $params['cli_auth'] = $clientAuthCode;

        $params['sign'] = hash($this->hashAlg, implode('', $params).$this->verificationCode);
        $params['api_password'] = $this->apiPass;

        $response = $this->postRequest($this->apiURL.$this->apiKey, $params);

        return $response;
    }

    /**
     * Validate all transaction parameters and throw TException if any error occurs
     * Add required fields sign and api password to config.
     *
     * @param string      $clientAuthCode  client auth code
     * @param string      $saleDescription sale description
     * @param float       $amount          amount
     * @param string      $currency        currency
     * @param string|null $orderID         order id
     * @param string      $lang            language
     * @param string      $method          sale method
     * @param array       $errors          validation errors
     *
     * @return array parameters for sale request
     *
     * @throws TException
     */
    private function saleValidateAndPrepareParams($clientAuthCode, $saleDescription, $amount, $currency, $orderID, $lang, $method, $errors = array())
    {
        $errors = array();

        if (!is_string($clientAuthCode) || strlen($clientAuthCode) === 0) {
            $errors[] = 'Client auth code is empty.';
        } else {
            if (strlen($clientAuthCode) !== 40) {
                $errors[] = 'Client auth code is invalid.';
            }
        }

        if (!is_string($saleDescription) || strlen($saleDescription) === 0) {
            $errors[] = 'Sale description is empty.';
        } else {
            if (strlen($saleDescription) > 128) {
                $errors[] = 'Sale description is too long. Max 128 characters.';
            }
        }

        if (!is_double($amount) && !is_float($amount) && !is_int($amount) && $amount <= 0) {
            $errors[] = 'Amount is invalid.';
        }

        if (!is_int($currency) && strlen($currency) != 3) {
            $errors[] = 'XCurrency is invalid.';
        }

        if (sizeof($errors) > 0) {
            throw new TException(sprintf('%s', implode(' ', $errors)));
        }

        $amount = number_format(str_replace(array(',', ' '), array('.', ''), $amount), 2, '.', '');

        $params = array(
            'method' => $method,
            'cli_auth' => $clientAuthCode,
            'desc' => $saleDescription,
            'amount' => $amount,
        );

        if ($currency) {
            $params['currency'] = $currency;
        }
        if ($orderID) {
            $params['order_id'] = $orderID;
        }
        if ($lang) {
            $params['language'] = $lang;
        }

        $params['sign'] = hash($this->hashAlg, implode('', $params).$this->verificationCode);
        $params['api_password'] = $this->apiPass;

        return $params;
    }

    /**
     * Prepare for register sale @see $this->registerSale.
     *
     * @param string      $clientName      client name
     * @param string      $clientEmail     client email
     * @param string      $saleDescription sale description
     * @param float       $amount          amount
     * @param string      $currency        currency
     * @param string|null $orderID         order id
     * @param bool        $onetimer
     * @param bool        $direct
     * @param string|null $saledata        encrypted credit card data
     * @param string      $lang
     *
     * @return bool|mixed
     *
     * @throws TException
     */
    private function registerSaleBase(
        $clientName,
        $clientEmail,
        $saleDescription,
        $amount,
        $currency = '985',
        $orderID = null,
        $onetimer = true,
        $direct = false,
        $saledata = null,
        $lang = 'pl'
    ) {
        $amount = number_format(str_replace(array(',', ' '), array('.', ''), $amount), 2, '.', '');

        if ($direct && !empty($saledata)) {
            $params = array(
                'method' => 'directsale',
                'card' => $saledata,
                'name' => $clientName,
                'email' => $clientEmail,
                'desc' => $saleDescription,
                'amount' => $amount,
            );
        } else {
            $params = array(
                'method' => 'register_sale',
                'name' => $clientName,
                'email' => $clientEmail,
                'desc' => $saleDescription,
                'amount' => $amount,
            );
        }
        if ($currency) {
            $params['currency'] = $currency;
        }
        if ($orderID) {
            $params['order_id'] = $orderID;
        }
        if ($onetimer) {
            $params['onetimer'] = '1';
        }
        if ($lang) {
            $params['language'] = $lang;
        }

        $params['sign'] = hash($this->hashAlg, implode('', $params).$this->verificationCode);
        $params['api_password'] = $this->apiPass;

        Util::log('Card request', print_r($params, true));

        $response = $this->postRequest($this->apiURL.$this->apiKey, $params);

        return $response;
    }

    /**
     * Execute post request to card API.
     *
     * @param string $url    url
     * @param array  $params
     *
     * @return bool|mixed
     */
    private function postRequest($url, $params = array())
    {
        $curlRes = Curl::doCurlRequest($url, $params);

        return json_decode($curlRes, true);
    }
}
