<?php

namespace tpay;

/**
 * Class PaymentSMS.
 */
class payment_sms
{
    /**
     * Url to verify SMS code.
     *
     * @var string
     */
    private $secureURL = 'http://sms.tpay.com/widget/verifyCode.php';

    /**
     * PaymentSMS class constructor.
     */
    public function __construct()
    {
        require_once dirname(__FILE__).'/util.php';
        Util::checkVersionPHP();
        Util::loadClass('curl');
    }

    /**
     * Get code sent by from Tpay SMS widget.
     * Validate code by sending cURL to Tpay server.
     *
     * @return bool
     *
     * @throws TException
     */
    public function verifyCode()
    {
        $codeToCheck = Util::post('tfCodeToCheck', 'string');
        $hash = Util::post('tfHash', 'string');

        if ($codeToCheck === false || $hash === false) {
            throw new TException('Invalid input data');
        }

        $postData = array(
            'tfCodeToCheck' => $codeToCheck,
            'tfHash' => $hash,
        );
        $response = Curl::doCurlRequest($this->secureURL, $postData);

        $data = explode("\n", $response);

        $status = (int) $data[0];
        $lifetime = rtrim($data[1]);

        if ($status === 1) {
            return true;
        } else {
            return false;
        }
    }
}
