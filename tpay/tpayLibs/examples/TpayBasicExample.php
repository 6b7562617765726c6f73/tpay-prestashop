<?php

/*
 * Created by tpay.com
 */

namespace tpayLibs\examples;

use tpayLibs\src\_class_tpay\PaymentForms\PaymentBasicForms;

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

}
