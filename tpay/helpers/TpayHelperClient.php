<?php
/**
 * NOTICE OF LICENSE.
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 *  @author    tpay.com
 *  @copyright 2010-2016 tpay.com
 *  @license   LICENSE.txt
 */

require_once _PS_MODULE_DIR_.'tpay/lib/_class_tpay/PaymentBasic.php';
require_once _PS_MODULE_DIR_.'tpay/lib/_class_tpay/payment_card.php';
require_once _PS_MODULE_DIR_.'tpay/lib/_class_tpay/payment_white_label.php';
require_once _PS_MODULE_DIR_.'tpay/lib/_class_tpay/payment_dac.php';

/**
 * Class TpayHelperClient.
 */
class TpayHelperClient extends Helper
{
    /**
     * Returns basic tpay client.
     *
     * @return \Tpay\PaymentBasic
     */
    public static function getBasicClient()
    {
        $merchantId = (int) Configuration::get('TPAY_ID');
        $merchantSecret = Configuration::get('TPAY_KEY');

        return new \Tpay\PaymentBasic($merchantId, $merchantSecret);
    }


    /**
     * Return merchant shop details.
     *
     * @return string
     */
    public static function getMerchantData()
    {
        $shopDetails = Configuration::getMultiple(
            array(
                'PS_SHOP_NAME',
                'PS_SHOP_ADDR1',
                'PS_SHOP_CODE',
                'PS_SHOP_CITY',
                'PS_SHOP_DETAILS'
            )
        );

        return implode(' ', $shopDetails);
    }

    /**
     * Return surcharge value for order.
     *
     * @param float $orderTotal order value
     *
     * @return bool|float false if surcharge is off
     */
    public static function getSurchargeValue($orderTotal)
    {
        $surcharge = false;

        $surchargeActive = (int) Configuration::get('TPAY_SURCHARGE_ACTIVE');
        if ($surchargeActive === 1) {
            $surchargeType = (int) Configuration::get('TPAY_SURCHARGE_TYPE');
            $surchargeValue = (float) Configuration::get('TPAY_SURCHARGE_VALUE');

            if ($surchargeType === TPAY_SURCHARGE_PERCENT) {
                $surcharge = ($orderTotal / 100) * $surchargeValue;
            } else {
                $surcharge = $surchargeValue;
            }
        }

        return $surcharge;
    }
}
