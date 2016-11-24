<?php

namespace tpay;

/**
 * Class PaymentCard.
 *
 * Class handles credit card payments through "Card API".
 * Depending on the chosen method:
 *  - client is redirected to card payment panel
 *  - card gate form is rendered
 *  - when user has saved card data only button is shown
 */
class payment_card
{
    /**
     * Merchant id.
     *
     * @var int
     */
    protected $merchantId = '[MERCHANT_ID]';

    /**
     * Merchant secret.
     *
     * @var string
     */
    private $merchantSecret = '[MERCHANT_SECRET]';

    /**
     * Card API key.
     *
     * @var string
     */
    private $apiKey = '[CARD_API_KEY]';

    /**
     * Card API password.
     *
     * @var string
     */
    private $apiPassword = '[CARD_API_PASSWORD]';

    /**
     * Card API code.
     *
     * @var string
     */
    private $code = '[CARD_API_CODE]';

    /**
     * Card RSA key.
     *
     * @var string
     */
    private $keyRSA = '[CARD_RSA_KEY]';

    /**
     * Card hash algorithm.
     *
     * @var string
     */
    private $hashAlg = '[CARD_HASH_ALG]';

    /**
     * Currency code.
     *
     * @var string
     */
    private $currency = '985';

    /**
     * Tpay payment url.
     *
     * @var string
     */
    private $apiURL = 'https://secure.tpay.com/cards/';

    /**
     * Tpay response IP.
     *
     * @var string
     */
    private $secureIP = array(
                            '176.119.38.175',
                        );

    /**
     * If false library not validate Tpay server IP.
     *
     * @var bool
     */
    private $validateServerIP = true;

    /**
     * PaymentCard class constructor for payment:
     * - card by panel
     * - card direct sale
     * - for saved cards.
     *
     * @param string|bool $merchantId     merchant id
     * @param string|bool $merchantSecret merchant secret
     * @param string|bool $apiKey         card api key
     * @param string|bool $apiPassword    card API password
     * @param string|bool $code           card API code
     * @param string|bool $hashAlg        card hash algorithm
     * @param string|bool $keyRSA         card RSA key
     */
    public function __construct($merchantId = false, $merchantSecret = false, $apiKey = false, $apiPassword = false, $code = false, $hashAlg = false, $keyRSA = false)
    {
        if ($merchantId !== false) {
            $this->merchantId = $merchantId;
        }
        if ($merchantSecret !== false) {
            $this->merchantSecret = $merchantSecret;
        }
        if ($apiKey !== false) {
            $this->apiKey = $apiKey;
        }
        if ($apiPassword !== false) {
            $this->apiPassword = $apiPassword;
        }
        if ($code !== false) {
            $this->code = $code;
        }
        if ($hashAlg !== false) {
            $this->hashAlg = $hashAlg;
        }
        if ($keyRSA !== false) {
            $this->keyRSA = $keyRSA;
        }

        require_once dirname(__FILE__).'/util.php';
        Util::loadClass('validate');
        Util::loadClass('exception');
        Util::loadClass('lang');
        Util::checkVersionPHP();

        Validate::validateMerchantId($this->merchantId);
        Validate::validateMerchantSecret($this->merchantSecret);

        Validate::validateCardApiKey($this->apiKey);
        Validate::validateCardApiPassword($this->apiPassword);
        Validate::validateCardCode($this->code);
        Validate::validateCardHashAlg($this->hashAlg);
        Validate::validateCardRSAKey($this->keyRSA);

        Util::loadClass('card_api');
    }

    /**
     * Disabling validation of payment notification server IP
     * Validation of Tpay server ip is very important.
     * Use this method only in test mode and be sure to enable validation in production.
     */
    public function disableValidationServerIP()
    {
        $this->validateServerIP = false;
    }

    /**
     * Enabling validation of payment notification server IP.
     */
    public function enableValidationServerIP()
    {
        $this->validateServerIP = true;
    }

    /**
     * Create HTML form for panel payment based on transaction config
     * More information about config fields @see Validate::$cardPaymentRequestFields.
     *
     * @param array $config transaction config
     *
     * @return string
     *
     * @throws TException
     */
    public function getTransactionForm($config)
    {
        $config = Validate::validateConfig(Validate::PAYMENT_TYPE_CARD, $config);

        $currency = isset($config['currency']) ? $config['currency'] : $this->currency;

        $api = new CardAPI($this->apiKey, $this->apiPassword, $this->code, $this->hashAlg);
        $apiResponse = $api->registerSale(
            $config['name'],
            $config['email'],
            $config['desc'],
            $config['amount'],
            $currency,
            $config['order_id']
        );

        Util::log('card register sale', print_r($apiResponse, true));
        if (!is_array($apiResponse) || !isset($apiResponse['result']) || !isset($apiResponse['sale_auth'])) {
            throw new TException('Invalid api response code');
        }

        $data = array(
            'action_url' => $this->apiURL,
            'merchant_id' => $this->merchantId,
            'sale_auth' => $apiResponse['sale_auth'],
        );

        return Util::parseTemplate('card/_tpl/payment_form', $data);
    }

    /**
     * Check cURL request from Tpay server after payment.
     * This method check server ip, required fields and md5 checksum sent by payment server.
     * Display information to prevent sending repeated notifications.
     *
     * @return mixed
     *
     * @throws TException
     */
    public function handleNotification()
    {
        Util::log('card handle notification', print_r($_POST, true));

        $notificationType = Util::post('type', 'string');
        if ($notificationType === 'sale') {
            $response = Validate::getResponse(Validate::PAYMENT_TYPE_CARD);
        } elseif ($notificationType === 'deregister') {
            $response = Validate::getResponse(Validate::CARD_DEREGISTER);
        } else {
            throw new TException('Unknown notification type');
        }

        if ($this->validateServerIP === true && $this->checkServer() === false) {
            throw new TException('Request is not from secure server');
        }

        echo json_encode(array('result' => '1'));

        if ($notificationType === 'sale' && $response['status'] === 'correct') {
            return array(
                'order_id' => $response['order_id'],
                'sign' => $response['sign'],
                'sale_auth' => $response['sale_auth'],
                'sale_date' => $response['date'],
                'test_mode' => $response['test_mode'],
                'card' => $response['card'],
            );
        } elseif ($notificationType === 'deregister') {
            return $response;
        } else {
            throw new TException('Incorrect payment');
        }
    }

    /**
     * Check if request is called from secure Tpay server.
     *
     * @return bool
     */
    private function checkServer()
    {
        if (!isset($_SERVER['REMOTE_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], $this->secureIP)) {
            return false;
        }

        return true;
    }

    /**
     * Get HTML form for direct sale gate. Using for payment in merchant shop.
     *
     * @param string $staticFilesURL      path to library static files
     * @param string $paymentRedirectPath payment redirect path
     *
     * @return string
     *
     * @throws TException
     */
    public function getDirectCardForm($staticFilesURL = '', $paymentRedirectPath = 'index.php')
    {
        $keyRSA = $this->keyRSA;

        if (!is_string($keyRSA) || $keyRSA === '') {
            throw new TException('Invalid api response code');
        }

        $data = array(
            'rsa_key' => $keyRSA,
            'static_files_url' => $staticFilesURL,
            'payment_redirect_path' => $paymentRedirectPath,
        );

        return Util::parseTemplate('card/_tpl/gate', $data);
    }

    /**
     * Get HTML form for saved card transaction. Using for payment in merchant shop.
     *
     * @param string $cliAuth         client auth sign form prev payment
     * @param string $desc            transaction description
     * @param float  $amount          amount
     * @param string $confirmationUrl url to send confirmation
     * @param string $orderId         order id
     * @param string $language        language
     * @param string $currency        currency
     *
     * @return string
     *
     * @throws TException
     */
    public function getCardSavedForm($cliAuth, $desc, $amount, $confirmationUrl, $orderId = '', $language = 'pl', $currency = '985')
    {
        $api = new CardAPI($this->apiKey, $this->apiPassword, $this->code, $this->hashAlg);

        $resp = $api->presale($cliAuth, $desc, $amount, $currency, $orderId, $language);

        Util::log('Card saved presale response', print_r($resp, true));

        if ((int) $resp['result'] === 1) {
            $data = array(
                'sale_auth' => $resp['sale_auth'],
                'confirmation_url' => $confirmationUrl,
                'order_id' => $orderId,
            );

            return Util::parseTemplate('card/_tpl/saved_card', $data);
        } else {
            throw new TException('Order data is invalid');
        }
    }

    /**
     * Card direct sale. Handle request from card gate form in merchant site
     * from method getDirectCardForm
     * Validate transaction config and all input fields.
     *
     * @param float  $orderAmount amount of payment
     * @param int    $orderID     order id
     * @param string $orderDesc   order description
     * @param string $currency    transaction currency
     *
     * @return bool|mixed
     *
     * @throws TException
     */
    public function directSale($orderAmount, $orderID, $orderDesc, $currency = '985')
    {
        $cardData = Util::post('carddata', 'string');
        $clientName = Util::post('client_name', 'string');
        $clientEmail = Util::post('client_email', 'string');
        $saveCard = Util::post('card_save', 'string');

        Util::log('Card direct post params', print_r($_POST, true));

        $oneTimeTransaction = ($saveCard !== 'on');
        $amount = number_format(str_replace(array(',', ' '), array('.', ''), $orderAmount), 2, '.', '');
        $amount = (float) $amount;

        $api = new CardAPI($this->apiKey, $this->apiPassword, $this->code, $this->hashAlg);

        $tmpConfig = array(
            'amount' => $amount,
            'name' => $clientName,
            'email' => $clientEmail,
            'desc' => $orderDesc,
            'order_id' => $orderID,
        );

        Validate::validateConfig(Validate::PAYMENT_TYPE_CARD_DIRECT, $tmpConfig);

        $response = $api->directSale(
            $clientName,
            $clientEmail,
            $orderDesc,
            $amount,
            $cardData,
            $currency,
            $orderID,
            $oneTimeTransaction
        );

        Util::log('card direct sale response', print_r($response, true));

        return $response;
    }

    /**
     * Register sale for client saved card.
     *
     * @param string $cliAuth  client auth sign
     * @param string $saleAuth client sale sign
     *
     * @return bool|mixed
     */
    public function cardSavedSale($cliAuth, $saleAuth)
    {
        $api = new CardAPI($this->apiKey, $this->apiPassword, $this->code, $this->hashAlg);

        return $api->sale($cliAuth, $saleAuth);
    }

    /**
     * Check md5 sum to validate Tpay response.
     * The values of variables that md5 sum includes are available only for
     * merchant and Tpay system.
     *
     * @param string $sign
     * @param string $testMode
     * @param string $saleAuth
     * @param string $orderId
     * @param string $card
     * @param float  $amount
     * @param string $saleDate
     * @param string $currency
     *
     * @throws TException
     */
    public function validateSign($sign, $testMode, $saleAuth, $orderId, $card, $amount, $saleDate, $currency = '985')
    {
        if ($sign !== hash($this->hashAlg, 'sale'.$testMode.$saleAuth.$orderId.$card.$currency.$amount.$saleDate.'correct'.$this->code)) {
            throw new TException('Card payment - invalid checksum');
        }
    }
}
