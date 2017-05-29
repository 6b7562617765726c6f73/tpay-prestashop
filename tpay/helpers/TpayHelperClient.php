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
 * @author    tpay.com
 * @copyright 2010-2016 tpay.com
 * @license   LICENSE.txt
 */

require_once _PS_MODULE_DIR_ . 'tpay/lib/_class_tpay/PaymentBasic.php';
require_once _PS_MODULE_DIR_ . 'tpay/lib/_class_tpay/PaymentCard.php';

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
        $merchantId = (int)Configuration::get('TPAY_ID');
        $merchantSecret = Configuration::get('TPAY_KEY');

        return new \Tpay\PaymentBasic($merchantId, $merchantSecret);
    }

    /**
     * Returns card tpay client.
     *
     * @param $midId
     * @return \Tpay\PaymentCard
     */
    public static function getCardClient($midId)
    {
        $apiKey = Configuration::get('TPAY_CARD_KEY' . $midId);
        $apiPass = Configuration::get('TPAY_CARD_PASS' . $midId);
        $verificationCode = Configuration::get('TPAY_CARD_CODE' . $midId);
        $hashType = Configuration::get('TPAY_CARD_HASH' . $midId);
        $keyRSA = Configuration::get('TPAY_CARD_RSA' . $midId);

        return new \Tpay\PaymentCard($apiKey, $apiPass, $verificationCode, $hashType, $keyRSA);
    }

    public static function getCardMidNumber($currency, $domain)
    {

        $counter = 10;
        $validMidId = array();
        $midForCurrency = '';
        $midPLN = '';
        $midId = 11;

        for ($i = 1; $i <= $counter; $i++) {
            if (Configuration::get('TPAY_CARD_DOMAIN' . $i) === $domain) {
                $validMidId[] = $i;
            }
        }
        for ($i = 0; $i < count($validMidId); $i++) {

            $midCurrency = explode(',', trim(Configuration::get('TPAY_CARD_CURRENCY' . $validMidId[$i]), ' '));
            $midType = Configuration::get('TPAY_CARD_MULTI_CURRENCY' . $validMidId[$i]);
            $midOn = Configuration::get('TPAY_CARD_MID_ACTIVE' . $validMidId[$i]);

            if (!(bool)$midType && $currency === 'PLN' && (bool)$midOn) {
                $midId = $validMidId[$i];
                $midPLN = $validMidId[$i];
                break;
            }
            foreach ($midCurrency as $key => $value) {
                if ((strcasecmp($midCurrency[$key], $currency) === 0
                        || strcasecmp($midCurrency[$key], filter_input(INPUT_POST, 'currency')) === 0)
                    && $midOn !== 0 && (int)$midType === 1
                ) {
                    $midId = $validMidId[$i];
                    $midForCurrency = $validMidId[$i];

                } elseif ($midCurrency[$key] === '' && $midOn !== 0) {
                    $midId = $validMidId[$i];
                }
            }
        }
        if ($midId === 11) {
            return false;
        } elseif (!empty($midForCurrency) && empty($midPLN)) {
            $midId = $midForCurrency;
        }

        return $midId;

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

        $surchargeActive = (int)Configuration::get('TPAY_SURCHARGE_ACTIVE');
        if ($surchargeActive === 1) {
            $surchargeType = (int)Configuration::get('TPAY_SURCHARGE_TYPE');
            $surchargeValue = (float)Configuration::get('TPAY_SURCHARGE_VALUE');

            if ($surchargeType === TPAY_SURCHARGE_PERCENT) {
                $surcharge = ($orderTotal / 100) * $surchargeValue;
            } else {
                $surcharge = $surchargeValue;
            }
        }

        return $surcharge;
    }
}
