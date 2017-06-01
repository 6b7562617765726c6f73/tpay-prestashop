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
    private $tpayClientConfig = array();
    private $paymentType = false;
    private $installments = false;

    public function postProcess()
    {
        $this->display_column_left = false;
        $this->context->controller->addCss(_MODULE_DIR_ . 'tpay/views/css/style.css');
        $cart = $this->context->cart;
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
            TPAY_PAYMENT_CARDS,
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
        if (($paymentType === TPAY_PAYMENT_CARDS) && (int)Configuration::get('TPAY_CARD_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
        if (($paymentType === TPAY_PAYMENT_INSTALLMENTS) && (int)Configuration::get('TPAY_INSTALLMENTS_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
        if ($paymentType === TPAY_PAYMENT_INSTALLMENTS) {
            $this->installments = true;
        }
        $this->paymentType = $paymentType;
        $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
        $surcharge = TpayHelperClient::getSurchargeValue($orderTotal);
        if (!empty($surcharge)) {
            $orderTotal += $surcharge;
            $this->context->smarty->assign(array(
                'surcharge' => number_format(str_replace(array(',', ' '), array('.', ''), $surcharge), 2, '.', ''),
            ));
        }
        $this->context->smarty->assign(array(
            'orderTotal' => number_format(str_replace(array(',', ' '), array('.', ''), $orderTotal), 2, '.', ''),
        ));

        try {
            if ($paymentType === TPAY_PAYMENT_BASIC || $paymentType === TPAY_PAYMENT_INSTALLMENTS) {
                $this->renderBasic();
            } else {
                $this->renderCard();
            }

        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Finalize basic tpay payment.
     */
    private function renderBasic()
    {

        $paymentViewType = (int)Configuration::get('TPAY_BANK_ON_SHOP');

        $showRegulations = (bool)(int)Configuration::get('TPAY_SHOW_REGULATIONS');

        $autoSubmit = false;

        if ($this->installments) {
            $autoSubmit = true;
            $this->setTemplate('paymentBasic.tpl');
        } else {
            switch ($paymentViewType) {
                case TPAY_VIEW_REDIRECT:
                    $autoSubmit = true;
                    $this->setTemplate('paymentBasic.tpl');
                    break;
                case TPAY_VIEW_ICONS:
                    $this->context->smarty->assign(array('showRegulations' => $showRegulations));
                    $this->setTemplate('paymentBanks.tpl');
                    break;
                case TPAY_VIEW_LIST:
                    $this->context->smarty->assign(array('showRegulations' => $showRegulations));
                    $this->setTemplate('paymentBanksList.tpl');
                    break;
                default:
                    $autoSubmit = true;
                    $this->setTemplate('paymentBasic.tpl');
                    break;
            }

        }
        $this->assignSmartyData($autoSubmit);

    }

    private function assignSmartyData($autoSubmit)
    {
        $tplDir = _PS_MODULE_DIR_ . 'tpay/views/templates/front';
        $blikOn = (bool)(int)Configuration::get('TPAY_BLIK_ACTIVE');
        $paymentConfig['merchant_id'] = (int)Configuration::get('TPAY_ID');
        $paymentConfig['regulation_url'] = 'https://secure.tpay.com/partner/pliki/regulamin.pdf';
        $cart = $this->context->cart;
        $productsVariables = array(
            'name',
            'price_wt',
            'cart_quantity',
            'total_wt',
        );
        $orderProductsDetails = array();
        $products = $cart->getProducts();
        $numberOfProducts = count($products);
        for ($i = 0; $i < $numberOfProducts; $i++) {
            foreach ($products[$i] as $key => $value) {
                if (in_array($key, $productsVariables)) {
                    $orderProductsDetails[$i][array_search($key,
                        $productsVariables)] = ($key === 'price_wt' || $key === 'total_wt') ?
                        number_format(str_replace(array(',', ' '), array('.', ''), $value), 2, '.', '') : $value;
                }
            }
            ksort($orderProductsDetails[$i]);
        }
        $addressDeliveryId = $cart->id_address_delivery;
        $addressInvoiceId = $cart->id_address_invoice;
        $InvAddress = new AddressCore($addressInvoiceId);
        $deliveryAddress = new AddressCore($addressDeliveryId);
        $invAddressIndexes = array(
            'company',
            'lastname',
            'firstname',
            'address1',
            'address2',
            'postcode',
            'city',
        );
        $invAddressData = array();
        foreach ($InvAddress as $key => $value) {
            if (in_array($key, $invAddressIndexes)) {
                $invAddressData[$key] = $value;
            }
        }
        $deliveryAddressData = array();
        foreach ($deliveryAddress as $key => $value) {
            if (in_array($key, $invAddressIndexes)) {
                $deliveryAddressData[$key] = $value;
            }
        }
        $this->context->smarty->assign(array(
            'paymentConfig'   => $paymentConfig,
            'tplDir'          => $tplDir,
            'autoSubmit'      => $autoSubmit,
            'blikOn'          => $blikOn,
            'products'        => $orderProductsDetails,
            'shippingCost'    => number_format(str_replace(array(',', ' '), array('.', ''),
                $cart->getTotalShippingCost()), 2, '.', ''),
            'invAddress'      => $invAddressData,
            'deliveryAddress' => $deliveryAddressData,
            'installments'    => $this->installments,
        ));
    }

    private function renderCard()
    {
        $midId = TpayHelperClient::getCardMidNumber($this->context->currency->iso_code,
            _PS_BASE_URL_ . __PS_BASE_URI__);
        $paymentCard = TpayHelperClient::getCardClient($midId);
        $this->context->smarty->assign(array(
            'form' => $paymentCard->getDirectCardForm(__PS_BASE_URI__ . 'modules/tpay/lib/',
                'payment?type=' . TPAY_PAYMENT_CARDS, false)
        ));
        $this->setTemplate('paymentCard.tpl');
        $this->assignSmartyData(false);
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
        $targetOrderState = (int)Configuration::get('TPAY_OWN_STATUS') === 1 ?
            Configuration::get('TPAY_OWN_ERROR') : Configuration::get('TPAY_ERROR');
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

}
