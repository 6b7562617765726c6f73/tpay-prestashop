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
 * include tpay client.
 */
require_once _PS_MODULE_DIR_ . 'tpay/tpayModel.php';
require_once _PS_MODULE_DIR_ . 'tpay/helpers/TpayHelperClient.php';

/**
 * Class TpayValidationModuleFrontController.
 */
class TpayValidationModuleFrontController extends ModuleFrontController
{
    private $tpayClient = false;
    private $tpayClientConfig = array();
    private $currentOrderId = 0;
    private $paymentType = false;
    private $installments = false;

    public function postProcess()
    {
        $this->display_column_left = false;
        $this->context->controller->addCss(_MODULE_DIR_ . 'tpay/views/css/style.css');

        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $customer = new Customer($cart->id_customer);
        $paymentType = Tools::getValue('type');

        $errorRedirectLink = $this->context->link->getPageLink('order', true, null, 'step=3');

        /*
         * check for basic fields
         */
        if (
            $cart->id_customer == 0
            ||
            $cart->id_address_delivery == 0
            ||
            $cart->id_address_invoice == 0
            ||
            !$this->module->active
        ) {
            Tools::redirect($errorRedirectLink);
        }

        /*
         * is customer loaded
         */
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect($errorRedirectLink);
        }

        /*
         * is valid payment type
         */
        if (!in_array($paymentType, array(
            TPAY_PAYMENT_BASIC,
            TPAY_PAYMENT_INSTALLMENTS,
        ))
        ) {
            Tools::redirect($errorRedirectLink);
        }
        /*
         * is payment active
         */
        if (($paymentType === TPAY_PAYMENT_BASIC) && (int)Configuration::get('TPAY_BASIC_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
        if (($paymentType === TPAY_PAYMENT_INSTALLMENTS) && (int)Configuration::get('TPAY_INSTALLMENTS_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
        if ($paymentType === TPAY_PAYMENT_INSTALLMENTS) {
            $this->installments = true;
        }
        $this->paymentType = $paymentType;

        if ((int)$this->context->cookie->last_order > 0) {
            $orderId = $this->context->cookie->last_order;
            $order = new Order($orderId);
            $orderTotal = (float)$order->total_paid;
        } else {
            $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
            $this->module->validateOrder(
                (int)$cart->id,
                Configuration::get('TPAY_NEW'),
                $orderTotal,
                $this->module->displayName,
                null,
                array(),
                (int)$currency->id,
                false,
                $customer->secure_key
            );
            $orderId = OrderCore::getOrderByCartId($cart->id);
            $this->context->cookie->last_order = $orderId;
            $this->context->cookie->__set('last_order', $orderId);
        }

        $surcharge = TpayHelperClient::getSurchargeValue($orderTotal);
        if (!empty($surcharge)) {
            $orderTotal += $surcharge;
            $this->context->smarty->assign([
                'surcharge' => $surcharge,
            ]);
        } else {
            $surcharge = 0.0;
        }
        $this->context->smarty->assign([
            'orderTotal' => $orderTotal,
        ]);
        if (empty($orderId)) {
            Tools::redirect($this->context->link->getModuleLink('tpay', 'ordererror'));
        }

        $this->currentOrderId = $orderId;
        $crc_sum = md5($cart->id . $this->context->cookie->mail . $customer->secure_key . time());

        try {
            /*
             * Set basic config fields
             */
            if (
                $paymentType === TPAY_PAYMENT_BASIC || $this->installments
            ) {
                $this->tpayClientConfig['kwota'] = $orderTotal;
                $this->tpayClientConfig['crc'] = $crc_sum;
            }
            /*
             * Insert order to db
             */
            TpayModel::insertOrder($orderId, $crc_sum, $paymentType, false, $surcharge);

            /*
             * Get required payment form
             */
            $this->renderBasic();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Finalize basic tpay payment.
     */
    private function renderBasic()
    {
        $this->initBasicClient();
        $orderId = $this->currentOrderId;
        $order = new Order($orderId);
        $paymentViewType = (int)Configuration::get('TPAY_BANK_ON_SHOP');
        $blikOn = (bool)(int)Configuration::get('TPAY_BLIK_ACTIVE');
        $showRegulations = (bool)(int)Configuration::get('TPAY_SHOW_REGULATIONS');
        $this->tpayClientConfig['wyn_url'] = $this->context->link->getModuleLink(
            'tpay',
            'confirmation',
            array('type' => TPAY_PAYMENT_BASIC)
        );
        $tplDir = _PS_MODULE_DIR_ . 'tpay/views/templates/front';
        $autoSubmit = false;

        $Client = $this->tpayClient;
        if ($this->installments) {
            $paymentConfig = $Client->getTransactionFormConfig($this->tpayClientConfig);
            $autoSubmit = true;
            $this->setTemplate('paymentBasic.tpl');
        } else {
            switch ($paymentViewType) {
                case TPAY_VIEW_REDIRECT:
                    $paymentConfig = $Client->getTransactionFormConfig($this->tpayClientConfig);
                    $autoSubmit = true;
                    $this->setTemplate('paymentBasic.tpl');
                    break;
                case TPAY_VIEW_ICONS:
                    $paymentConfig = $Client->getBankSelectionFormConfig($this->tpayClientConfig, $showRegulations);
                    $this->context->smarty->assign(['showRegulations' => $showRegulations]);
                    $this->setTemplate('paymentBanks.tpl');
                    break;
                case TPAY_VIEW_LIST:
                    $paymentConfig = $Client->getBankSelectionFormConfig($this->tpayClientConfig, $showRegulations);
                    $this->context->smarty->assign(['showRegulations' => $showRegulations]);
                    $this->setTemplate('paymentBanksList.tpl');
                    break;
                default:
                    $paymentConfig = $Client->getTransactionFormConfig($this->tpayClientConfig);
                    $autoSubmit = true;
                    $this->setTemplate('paymentBasic.tpl');
                    break;
            }

        }
        $productsVariables = [
            'product_name',
            'product_price_wt',
            'cart_quantity',
            'total_wt',

        ];
        $orderProductsDetails = [];
        $numberOfProducts = count($order->getCartProducts());
        for ($i = 0; $i < $numberOfProducts; $i++) {
            foreach ($order->getCartProducts()[$i] as $key => $value) {
                if (in_array($key, $productsVariables)) {
                    $orderProductsDetails[$i][array_search($key,
                        $productsVariables)] = $key === 'total_wt' || $key === 'product_price_wt' ?
                        (double)$value : $value;
                }
            }
            ksort($orderProductsDetails[$i]);
        }
        $addressDeliveryId = $order->id_address_delivery;
        $addressInvoiceId = $order->id_address_invoice;
        $InvAddress = new AddressCore($addressInvoiceId);
        $deliveryAddress = new AddressCore($addressDeliveryId);
        $invAddressIndexes = [
            'company',
            'lastname',
            'firstname',
            'address1',
            'address2',
            'postcode',
            'city',
        ];
        $invAddressData = [];
        foreach ($InvAddress as $key => $value) {
            if (in_array($key, $invAddressIndexes)) {
                $invAddressData[$key] = $value;
            }
        }
        $deliveryAddressData = [];
        foreach ($deliveryAddress as $key => $value) {
            if (in_array($key, $invAddressIndexes)) {
                $deliveryAddressData[$key] = $value;
            }
        }
        $this->context->smarty->assign(array(
            'paymentConfig' => $paymentConfig,
            'tplDir'        => $tplDir,
            'autoSubmit'    => $autoSubmit,
            'blikOn'        => $blikOn,
            'orderTest'     => '123',

            'products'        => $orderProductsDetails,
            'shippingCost'    => (double)$order->total_shipping_tax_incl,
            'invAddress'      => $invAddressData,
            'deliveryAddress' => $deliveryAddressData,
        ));
    }

    private function initBasicClient()
    {
        $this->tpayClient = TpayHelperClient::getBasicClient();

        $this->tpayClientConfig += array(
            'opis'         => 'Zamówienie nr ' . $this->currentOrderId . '. Klient ' .
                $this->context->cookie->customer_firstname . ' ' . $this->context->cookie->customer_lastname,
            'pow_url'      => $this->context->link->getModuleLink('tpay', 'order-success'),
            'pow_url_blad' => $this->context->link->getModuleLink('tpay', 'order-error'),
            'email'        => $this->context->cookie->email,
            'imie'         => $this->context->cookie->customer_firstname,
            'nazwisko'     => $this->context->cookie->customer_lastname,
            'wyn_url'      => $this->context->link->getModuleLink('tpay', 'confirmation'),
        );
        if ($this->installments) {
            $this->tpayClientConfig += array(
                'kanal'               => 49,
                'direct'              => 1,
                'akceptuje_regulamin' => 1
            );
        }
    }

    /**
     * Handles order exceptions.
     *
     * @param $exception
     */
    private function handleException($exception)
    {
        $this->context->cookie->last_order = false;
        $this->context->cookie->__unset('last_order');
        $debug_on = (bool)(int)Configuration::get('TPAY_DEBUG');

        /**
         * prepare error message.
         */
        $msg = 'config array ' . nl2br(print_r($this->tpayClientConfig, true)) . "\n";
        $msg .= 'exception ' . nl2br(print_r($exception, true)) . "\n";

        /**
         * set order status to payment error.
         */
        $orderHistory = new OrderHistory();

        $lastOrderState = $orderHistory->getLastOrderState($this->currentOrderId);
        $lastOrderState = (int)$lastOrderState->id;
        $targetOrderState = (int)Configuration::get('TPAY_ERROR');

        if ($lastOrderState !== $targetOrderState) {
            $orderHistory->id_order = $this->currentOrderId;
            $orderHistory->changeIdOrderState($targetOrderState, $this->currentOrderId);
            $orderHistory->add();
        }

        if ($debug_on) {
            echo '<pre>';
            print_r($msg);
            print_r(debug_backtrace());
            echo '</pre>';
            die();
        }

        /*
         * redirect client to error page
         */
        Tools::redirect($this->context->link->getModuleLink('tpay', 'ordererror'));
    }

    /**
     * Check if module is available.
     *
     * @return bool module available
     */
    private function validateModuleAvailable()
    {
        // Check that this payment option is still available in case the customer changed his address
        // just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'tpay') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        return $authorized;
    }

}
