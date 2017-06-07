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

use tpay\Curl;

require_once _PS_MODULE_DIR_ . '/tpay/helpers/TpayHelperClient.php';
require_once _PS_MODULE_DIR_ . 'tpay/tpayModel.php';

/**
 * Class TpayPaymentModuleFrontController.
 */
class TpayPaymentModuleFrontController extends ModuleFrontController
{
    const TPAY_URL = 'https://secure.tpay.com';

    private $tpayClientConfig;

    private $tpayClient;

    private $currentOrderId;

    private $tpayCardClient;

    public function initContent()
    {
        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $customer = new Customer($cart->id_customer);
        $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);

        $crc_sum = md5($cart->id . $this->context->cookie->mail . $customer->secure_key . time());

        $surcharge = TpayHelperClient::getSurchargeValue($orderTotal);
        if (!empty($surcharge)) {
            $orderTotal += $surcharge;
        } else {
            $surcharge = 0.0;
        }

        $this->module->validateOrder(
            (int)$cart->id,
            (int)Configuration::get('TPAY_OWN_STATUS') === 1 ?
                Configuration::get('TPAY_OWN_WAITING') : Configuration::get('TPAY_NEW'),
            $orderTotal,
            $this->module->displayName,
            null,
            array(),
            (int)$currency->id,
            false,
            $customer->secure_key
        );
        $orderId = OrderCore::getOrderByCartId($cart->id);
        $this->currentOrderId = $orderId;
        $this->tpayClientConfig['kwota'] = number_format(str_replace(array(',', ' '), array('.', ''),
            $orderTotal), 2, '.', '');
        $this->tpayClientConfig['crc'] = $crc_sum;
        $paymentType = 'basic';
        /*
         * Insert order to db
         */
        TpayModel::insertOrder($orderId, $crc_sum, $paymentType, false, $surcharge);
        $this->initBasicClient();

        if (Tools::getValue('type') === TPAY_PAYMENT_CARDS) {
            $this->processCardPayment($orderId);
        } else {
            $this->redirectToPayment();
        }


    }

    private function initBasicClient()
    {
        $this->tpayClient = TpayHelperClient::getBasicClient();
        $cart = $this->context->cart;
        $addressInvoiceId = $cart->id_address_invoice;
        $billingAddress = new AddressCore($addressInvoiceId);
        $this->tpayClientConfig += array(
            'opis'                => 'Zamówienie nr ' . $this->currentOrderId . '. Klient ' .
                $this->context->cookie->customer_firstname . ' ' . $this->context->cookie->customer_lastname,
            'pow_url'             => $this->context->link->getModuleLink('tpay', 'order-success'),
            'pow_url_blad'        => $this->context->link->getModuleLink('tpay', 'order-error'),
            'email'               => $this->context->cookie->email,
            'imie'                => $billingAddress->firstname,
            'nazwisko'            => $billingAddress->lastname,
            'telefon'             => $billingAddress->phone,
            'adres'               => $billingAddress->address1,
            'miasto'              => $billingAddress->city,
            'kod'                 => $billingAddress->postcode,
            'wyn_url'             => $this->context->link->getModuleLink('tpay', 'confirmation',
                array('type' => TPAY_PAYMENT_BASIC)),
            'akceptuje_regulamin' => (int)Tools::getValue('regulations'),

        );
        if ((int)Tools::getValue('channel') > 0) {
            $this->tpayClientConfig += array('kanal' => (int)Tools::getValue('channel'));
        }

    }

    private function processCardPayment($orderId)
    {
        $midId = TpayHelperClient::getCardMidNumber($this->context->currency->iso_code,
            _PS_BASE_URL_ . __PS_BASE_URI__);
        $this->tpayCardClient = TpayHelperClient::getCardClient($midId);

        $response = $this->tpayCardClient->secureSale($this->tpayClientConfig['kwota'],
            $midId . '*tpay*' . $this->tpayClientConfig['crc'],
            $this->tpayClientConfig['opis'],
            $this->context->currency->iso_code_num, true, $this->context->language->iso_code,
            $this->tpayClientConfig['pow_url'],
            $this->tpayClientConfig['pow_url_blad']);
        if (isset($response['result']) && (int)$response['result'] === 1) {

            $this->tpayCardClient->validateSign($response['sign'], $response['sale_auth'], $response['card'],
                $this->tpayClientConfig['kwota'], $response['date'], 'correct', $this->context->currency->iso_code_num,
                isset($response['test_mode']) ? '1' : '', '', '');
            $this->setOrderAsConfirmed($orderId, false);
            Tools::redirect($this->tpayClientConfig['pow_url']);

        } elseif (isset($response['3ds_url'])) {
            Tools::redirect($response['3ds_url']);
        } else {
            $this->setOrderAsConfirmed($orderId, true);
            if (Configuration::get('TPAY_DEBUG') === 1) {
                var_dump($response);
            } else {
                Tools::redirect($this->tpayClientConfig['pow_url_blad']);
            }
        }
    }

    /**
     * Update order status.
     *
     * @param int $orderId
     * @param bool $error change to error status flag
     */
    private function setOrderAsConfirmed($orderId, $error = false)
    {
        $orderHistory = new OrderHistory();

        $lastOrderState = $orderHistory->getLastOrderState($orderId);
        $lastOrderState = (int)$lastOrderState->id;
        if ((int)Configuration::get('TPAY_OWN_STATUS') === 1) {
            $targetOrderState = !$error ? Configuration::get('TPAY_OWN_PAID') : Configuration::get('TPAY_OWN_ERROR');
        } else {
            $targetOrderState = !$error ? Configuration::get('TPAY_CONFIRMED') : Configuration::get('TPAY_ERROR');
        }

        if ($lastOrderState !== $targetOrderState) {
            $orderHistory->id_order = $orderId;
            $orderHistory->changeIdOrderState($targetOrderState, $orderId);
            $orderHistory->add();
        }
    }

    private function redirectToPayment()
    {

        if (Tools::getValue('blikCode') && (is_int((int)(Tools::getValue('blikCode'))))) {
            $this->processBlikPayment($this->tpayClientConfig);
        } else {

            $tpayBasicClient = TpayHelperClient::getBasicClient();
            echo $tpayBasicClient->getTransactionForm($this->tpayClientConfig);

        }
    }

    private function processBlikPayment($data)
    {
        $data['kanal'] = 64;
        $data['akceptuje_regulamin'] = 1;
        $tpayApiClient = TpayHelperClient::getApiClient();

        $resp = $tpayApiClient->create($data);

        if ((int)$resp['result'] === 1) {
            $blikData = array();
            $blikData['code'] = Tools::getValue('blikCode');
            $blikData['title'] = $resp['title'];

            $resp = $tpayApiClient->handleBlikPayment($blikData);

            if ($resp) {
                $pow_url = $data['pow_url'];
                Tools::redirect($pow_url);
            } else {
                $pow_url_blad = $data['pow_url_blad'];
                Tools::redirect($pow_url_blad);
            }
            die();
        } else {
            $pow_url_blad = $data['pow_url_blad'];
            Tools::redirect($pow_url_blad);
        }
    }
}
