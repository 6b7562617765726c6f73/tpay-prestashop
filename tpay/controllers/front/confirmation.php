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
use tpayLibs\src\_class_tpay\Utilities\Util;

/**
 * include tpay client and model functions.
 */
require_once _PS_MODULE_DIR_ . 'tpay/tpayModel.php';
require_once _PS_MODULE_DIR_ . 'tpay/helpers/TpayHelperClient.php';
require_once _PS_MODULE_DIR_ . 'tpay/helpers/TpayOrderStatusHandler.php';

/**
 * Class ConfirmationModuleFrontController.
 */
class TpayConfirmationModuleFrontController extends ModuleFrontController
{
    private $tpayClient = false;

    private $paymentType = false;

    private $tpayPaymentId;

    /**
     * @var TpayOrderStatusHandler
     */
    private $statusHandler;

    /**
     * Hook header for confirmation processing.
     *
     * @return bool
     */
    public function initHeader()
    {
        $this->statusHandler = new TpayOrderStatusHandler();
        $paymentType = Tools::getValue('type');

        switch ($paymentType) {
            case TPAY_PAYMENT_INSTALLMENTS:
            case TPAY_PAYMENT_BASIC:
                $this->paymentType = TPAY_PAYMENT_BASIC;
                $this->initBasicClient();
                $this->confirmPaymentBasic();
                break;
            case 'sale':
                $this->initCardClient();
                $this->confirmPaymentCard();
                break;
            default:
                die('incorrect payment type');
        }
    }

    private function initBasicClient()
    {
        $this->tpayClient = TpayHelperClient::getBasicValidator();
        $this->setServerValidation();
    }

    /**
     * Confirm basic payment.
     *
     * @return bool
     */
    private function confirmPaymentBasic()
    {
        try {
            $orderRes = $this->tpayClient->checkPayment($this->paymentType);
            $this->tpayPaymentId = $orderRes['tr_id'];
            $orderData = TpayModel::getOrderIdAndSurcharge($orderRes['tr_crc']);
            $orderId = (int)$orderData['tj_order_id'];

            if ($orderId === 0) {
                return false;
            }
            $order = new Order($orderId);
            $orderTotal = (float)number_format($order->getOrdersTotalPaid(), 2, '.', '');
            $orderTotal === (float)number_format($orderRes['tr_paid'], 2, '.', '') ?
                $this->statusHandler->setOrdersAsConfirmed($orderId, $this->tpayPaymentId) :
                $this->statusHandler->setOrdersAsConfirmed($orderId, $this->tpayPaymentId, true);
            die();
        } catch (Exception $e) {
            Util::log('exception in payment confirmation', $e->getMessage());
            $log = array(
                'e'     => $e,
                'post'  => $_POST,
                'order' => isset($orderData) ? $orderData : array(),
            );

            $debug_on = (bool)(int)Configuration::get('TPAY_DEBUG');

            if ($debug_on) {
                echo '<pre>';
                var_dump($log);
                echo '</pre>';
                die();
            }
            return false;
        }
    }

    private function initCardClient()
    {
        $midId = explode('*tpay*', Tools::getValue('order_id'));
        $this->tpayClient = TpayHelperClient::getCardValidator($midId[0]);
        $this->setServerValidation();
    }

    private function setServerValidation()
    {
        $checkIp = (bool)(int)Configuration::get('TPAY_CHECK_IP');
        $checkProxy = (bool)(int)Configuration::get('TPAY_CHECK_PROXY');
        if (!$checkIp) {
            $this->tpayClient->disableValidationServerIP();
        }
        if ($checkProxy) {
            $this->tpayClient->enableForwardedIPValidation();
        }
    }

    /**
     * Confirm payment.
     *
     * @return bool
     */
    private function confirmPaymentCard()
    {
        try {
            $orderRes = $this->tpayClient->handleNotification();
            $this->tpayPaymentId = $orderRes['sale_auth'];
            $tpayOrderId = explode('*tpay*', $orderRes['order_id']);
            $orderData = TpayModel::getOrderIdAndSurcharge($tpayOrderId[1]);
            $orderId = (int)$orderData['tj_order_id'];
            if ($orderId === 0) {
                throw new Exception('Order ID value is 0!');
            }
            $surcharge = (float)$orderData['tj_surcharge'];
            $order = new Order($orderId);
            $currency = (new Currency($order->id_currency));
            $currency = $currency->getCurrency($order->id_currency);
            $orderTotal = round($order->getOrdersTotalPaid() + $surcharge, 2);
            $this->tpayClient->setAmount((double)$orderTotal)
                ->setCurrency($currency['iso_code_num'])
                ->setOrderID($orderRes['order_id'])
                ->validateCardSign($orderRes['sign'], $orderRes['sale_auth'], $orderRes['card'],
                    $orderRes['date'], 'correct', isset($orderRes['test_mode']) ? '1' : '');
            $this->statusHandler->setOrdersAsConfirmed($orderId, $this->tpayPaymentId);
            die();
        } catch (Exception $e) {
            Util::log('exception in payment confirmation', $e->getMessage());
            $log = array(
                'e'     => $e,
                'post'  => $_POST,
                'order' => isset($orderData) ? $orderData : array(),
            );

            $debug_on = (bool)(int)Configuration::get('TPAY_CARD_DEBUG');

            if ($debug_on) {
                echo '<pre>';
                var_dump($log);
                echo '</pre>';
                die;
            }

            return false;
        }
    }

}
