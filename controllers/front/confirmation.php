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

/**
 * include tpay client and model functions.
 */
require_once _PS_MODULE_DIR_.'tpay/tpayModel.php';
require_once _PS_MODULE_DIR_.'tpay/helpers/TpayHelperClient.php';

/**
 * Class ConfirmationModuleFrontController.
 */
class TpayConfirmationModuleFrontController extends ModuleFrontController
{
    private $tpayClient = false;
    private $paymentType = false;

    /**
     * Hook header for confirmation processing.
     *
     * @return bool
     */
    public function initHeader()
    {
        $paymentType = Tools::getValue('type');

        if (!in_array(
            $paymentType,
            array(
                TPAY_PAYMENT_BASIC,

            )
        )) {
            Tools::redirect('/');
        }

        switch ($paymentType) {
            case TPAY_PAYMENT_BASIC:
                $paymentType = TPAY_PAYMENT_BASIC;
                $this->initBasicClient();
                break;
            default:
                die('incorrect');
        }

        $this->paymentType = $paymentType;

        $this->confirmPayment();
        die;
    }

    private function initBasicClient()
    {
        $this->tpayClient = TpayHelperClient::getBasicClient();
        $checkIp = (bool)(int)Configuration::get('TPAY_CHECK_IP');
        if (!$checkIp) {
            $this->tpayClient->disableValidationServerIP();
        }
    }

    private function initCardClient()
    {
        $this->tpayClient = TpayHelperClient::getCardClient();
        $checkIp = (bool)(int)Configuration::get('TPAY_CHECK_IP');
        if (!$checkIp) {
            $this->tpayClient->disableValidationServerIP();
        }
    }

    private function initSzkwalClient()
    {
        $this->tpayClient = TpayHelperClient::getWhiteLabelClient();
    }

    /**
     * Confirm payment.
     *
     * @return bool
     */
    private function confirmPayment()
    {
        try {

                $orderRes = $this->tpayClient->checkPayment($this->paymentType);
                $orderData = TpayModel::getOrderIdAndSurcharge($orderRes['tr_crc']);
                $orderId = (int)$orderData['tj_order_id'];
                $surcharge = (float)$orderData['tj_surcharge'];
                $order = new Order($orderId);

                $orderTotal = round((float)$order->total_paid + $surcharge, 2);
                $orderTotal = number_format($orderTotal, 2, '.', '');

                $this->tpayClient->validateSign(
                    $orderRes['md5sum'],
                    $orderRes['tr_id'],
                    $orderTotal,
                    $orderRes['tr_crc']
                );


            if ($orderId === 0) {
                return false;
            }

            $this->setOrderAsConfirmed($orderId);

            return true;
        } catch (Exception $e) {
            $log = array(
                'e' => $e,
                'post' => $_POST,
                'order' => isset($orderData) ? $orderData : array(),
            );

            $debug_on = (bool)(int)Configuration::get('TPAY_DEBUG');

            if ($debug_on) {
                echo '<pre>';
                var_dump($log);
                echo '</pre>';
                die;
            }

            return false;
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
        $targetOrderState = !$error ? (int)Configuration::get('TPAY_CONFIRMED') : (int)Configuration::get('TPAY_ERROR');

        if ($lastOrderState !== $targetOrderState) {
            $orderHistory->id_order = $orderId;
            $orderHistory->changeIdOrderState($targetOrderState, $orderId);
            $orderHistory->add();
        }
    }

    /**
     * Confirmation of card form payment.
     *
     * @throws PrestaShopException
     */
    private function confirmCardForm()
    {
        $this->context->cookie->last_order = false;
        $this->context->cookie->__unset('last_order');

        $this->initCardClient();

        $orderId = (int)Tools::getValue('order');
        $hash = Tools::getValue('hash');

        if (!Validate::isInt($orderId) || $orderId <= 0) {
            Tools::redirect($this->context->link->getModuleLink('tpay', 'order-error'));
        }

        if (
            !Validate::isMd5($hash)
            ||
            md5(
                $this->context->cookie->customer_firstname.
                $this->context->cookie->customer_lastname.
                $this->context->cookie->email
            ) !== $hash
        ) {
            Tools::redirect('/');
        }

        $order = new Order($orderId);
        $orderTotal = (float)$order->total_paid;
        $crc = TpayModel::getHash($orderId);

        $cardCurrencies = unserialize(TPAY_CARD_CURRENCIES);
        $currency = $cardCurrencies[$this->context->currency->iso_code];

        $result = $this->tpayClient->directSale(
            $orderTotal,
            $crc,
            'Zamówienie nr '.$orderId.'. Klient '.$this->context->cookie->customer_firstname.' '
            .$this->context->cookie->customer_lastname,
            $currency
        );

        if ($result['status'] === 'correct') {
            $this->setOrderAsConfirmed($orderId);
            Tools::redirect($this->context->link->getModuleLink('tpay', 'ordersuccess'));
        } else {
            Tools::redirect($this->context->link->getModuleLink('tpay', 'ordererror'));
        }
    }

    /**
     * Confirmation of user saved card payment.
     */
    private function confirmCardSaved()
    {
    }
}
