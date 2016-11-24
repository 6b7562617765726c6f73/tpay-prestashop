<?php

namespace tpay;

/**
 * Class PaymentBasic.
 *
 * Class handles bank transfer payment through Tpay panel
 */
class PaymentBasic
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
     * API key.
     *
     * @var string
     */

    protected $apiURL = 'https://secure.tpay.com';

    /**
     * Tpay response IP.
     *
     * @var string
     */
    private $secureIP = array(
                            '195.149.229.109',
                            '148.251.96.163',
                            '178.32.201.77',
                            '46.248.167.59',
                            '46.29.19.106'
                        );

    /**
     * If false library not validate Tpay server IP.
     *
     * @var bool
     */
    private $validateServerIP = true;

    /**
     * Path to template directory.
     *
     * @var string
     */
    private $templateDir = 'common/_tpl/';

    /**
     * URL to Tpay regulations file.
     *
     * @var string
     */
    private $regulationURL = 'https://secure.tpay.com/regulamin.pdf';

    /**
     * PaymentBasic class constructor for payment:
     * - basic from Tpay panel
     * - with bank selection in merchant shop
     * - eHat.
     *
     * @param string|bool $merchantId     merchant id
     * @param string|bool $merchantSecret merchant secret
     */
    public function __construct($merchantId = false, $merchantSecret = false)
    {
        if ($merchantId !== false) {
            $this->merchantId = $merchantId;
        }
        if ($merchantSecret !== false) {
            $this->merchantSecret = $merchantSecret;
        }

        require_once dirname(__FILE__).'/util.php';

        Util::loadClass('curl');
        Util::loadClass('validate');
        Util::loadClass('exception');
        Util::loadClass('lang');
        Util::checkVersionPHP();
        Validate::validateMerchantId($this->merchantId);
        Validate::validateMerchantSecret($this->merchantSecret);
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
     * Check cURL request from Tpay server after payment.
     * This method check server ip, required fields and md5 checksum sent by payment server.
     * Display information to prevent sending repeated notifications.
     *
     * @param string $paymentType optional payment type default is 'basic'
     *
     * @throws TException
     *
     * @return array
     */
    public function checkPayment($paymentType = Validate::PAYMENT_TYPE_BASIC)
    {
        Util::log('check basic payment', '$_POST: '."\n".print_r($_POST, true));

        $res = Validate::getResponse($paymentType);

        $checkMD5 = $this->checkMD5(
            $res['md5sum'],
            $res['tr_id'],
            number_format($res['tr_amount'], 2, '.', ''),
            $res['tr_crc']
        );
        Util::logLine('Check MD5: '.(int) $checkMD5);

        if ($this->validateServerIP === true && $this->checkServer() === false) {
            throw new TException('Request is not from secure server');
        }

        if ($checkMD5 === false) {
            throw new TException('MD5 checksum is invalid');
        }
        echo 'TRUE';

        return $res;
    }

    /**
     * Create HTML form for EHat payment based on transaction config
     * More information about config fields @see Validate::$panelPaymentRequestFields.
     *
     * @param array $config transaction config
     *
     * @return string
     */
    public function getEHatForm($config)
    {
        $config = $this->prepareConfig($config);

        $config['kanal'] = 58;
        $config['akceptuje_regulamin'] = 1;

        $data = array(
            'action_url' => $this->apiURL,
            'fields' => $config,
        );

        $form = Util::parseTemplate($this->templateDir.'payment_form', $data);

        return $form;
    }

    /**
     * Create HTML form for basic panel payment based on transaction config
     * More information about config fields @see Validate::$panelPaymentRequestFields.
     *
     * @param array $config transaction config
     *
     * @return string
     */
    public function getTransactionForm($config)
    {
        $config = $this->prepareConfig($config);

        $data = array(
            'action_url' => $this->apiURL,
            'fields' => $config,
        );

        $form = Util::parseTemplate($this->templateDir.'payment_form', $data);

        return $form;
    }

    /**
     * Create HTML form for payment with bank selection based on transaction config
     * More information about config fields @see Validate::$panelPaymentRequestField.
     *
     * @param array $config          transaction config
     * @param bool  $smallList       type of bank selection list big icons or small form with select
     * @param bool  $showRegulations show accept regulations input
     *
     * @return string
     *
     * @throws TException
     */
    public function getBankSelectionForm($config, $smallList = false, $showRegulations = true)
    {
        $config = $this->prepareConfig($config);
        $config['kanal'] = 0;
        $config['akceptuje_regulamin'] = ($showRegulations) ? 0 : 1;

        $data = array(
            'action_url' => $this->apiURL,
            'fields' => $config,
        );

        $form = Util::parseTemplate($this->templateDir.'payment_form', $data);

        $data = array(
            'merchant_id' => $this->merchantId,
            'regulation_url' => $this->regulationURL,
            'show_regulations_checkbox' => $showRegulations,
            'form' => $form,
        );
        if ($smallList) {
            $templateFile = 'bank_selection_list';
        } else {
            $templateFile = 'bank_selection';
        }
        $bankSelectionForm = Util::parseTemplate($this->templateDir.$templateFile, $data);

        return $bankSelectionForm;
    }

    /**
     * Check md5 sum to validate Tpay response.
     * The values of variables that md5 sum includes are available only for
     * merchant and Tpay system.
     *
     * @param string $md5sum            md5 sum received from Tpay
     * @param string $transactionId     transaction id
     * @param float  $transactionAmount transaction amount
     * @param string $crc               transaction crc
     *
     * @return bool
     */
    private function checkMD5($md5sum, $transactionId, $transactionAmount, $crc)
    {
        if (!is_string($md5sum) || strlen($md5sum) !== 32) {
            return false;
        }

        return $md5sum === md5($this->merchantId.$transactionId.$transactionAmount.$crc.$this->merchantSecret);
    }

    /**
     * Check md5 sum to confirm value of payment amount.
     *
     * @param string $md5sum            md5 sum received from Tpay
     * @param string $transactionId     transaction id
     * @param string $transactionAmount transaction amount
     * @param string $crc               transaction crc
     *
     * @throws TException
     */
    public function validateSign($md5sum, $transactionId, $transactionAmount, $crc)
    {
        if ($md5sum !== md5($this->merchantId.$transactionId.$transactionAmount.$crc.$this->merchantSecret)) {
            throw new TException('Invalid checksum');
        }
    }

    /**
     * Validate passed payment config and add required elements with merchant id and md5 sum
     * More information about config fields @see Validate::$panelPaymentRequestField.
     *
     * @param array $config transaction config
     *
     * @return array
     *
     * @throws TException
     */
    private function prepareConfig($config)
    {
        $ready = Validate::validateConfig(Validate::PAYMENT_TYPE_BASIC, $config);

        $ready['md5sum'] = md5($this->merchantId.$ready['kwota'].$ready['crc'].$this->merchantSecret);
        $ready['id'] = $this->merchantId;

        return $ready;
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
}
