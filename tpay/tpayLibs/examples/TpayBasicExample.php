<?php

/*
 * Created by tpay.com
 */

namespace tpayLibs\examples;

use tpayLibs\src\_class_tpay\PaymentForms\PaymentBasicForms;
use tpayLibs\src\_class_tpay\Utilities\Util;

include_once 'config.php';
include_once 'loader.php';

class TpayBasicExample extends PaymentBasicForms
{
    public function __construct($id, $secret)
    {
        $this->merchantSecret = $secret;
        $this->merchantId = $id;
        parent::__construct();
    }

    /**
     * @param array $config transaction config
     * @param bool $redirect redirect automatically
     * @param string $actionURL
     * @return string
     */
    public function getTransactionForm($config = [], $redirect = false, $actionURL = null) {
        if (!empty($config)) {
            $config = $this->prepareConfig($config);
        }
        $data = [
            static::ACTION_URL => is_null($actionURL) ? $this->panelURL : (string)$actionURL,
            static::FIELDS => $config,
            'redirect' => $redirect,
        ];

        return Util::parseTemplate(static::PAYMENT_FORM, $data);
    }

}
