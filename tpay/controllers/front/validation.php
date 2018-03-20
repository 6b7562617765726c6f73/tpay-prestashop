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
    private $displayPrecision;
    private $Util;

    public function postProcess()
    {
        $this->displayPrecision = (int)Configuration::get('PS_PRICE_DISPLAY_PRECISION');
        $this->display_column_left = false;
        $this->context->controller->addCss(_MODULE_DIR_ . 'tpay/views/css/style.css?3');
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $paymentType = Tools::getValue('type');
        $errorRedirectLink = $this->context->link->getPageLink('order', true, null, 'step=3');
        $this->context->smarty->assign(array(
            'Tpay_PS17' => TPAY_PS_17,
        ));
        $this->checkForErrors($cart, $errorRedirectLink, $customer, $paymentType);
        if ($paymentType === TPAY_PAYMENT_INSTALLMENTS) {
            $this->installments = true;
        }
        $this->paymentType = $paymentType;
        $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
        $this->setOrderTotal($orderTotal);
        $language = $this->context->language->iso_code === 'pl' ? 'pl' : 'en';
        $this->Util = (new Util)->setLanguage($language)->setPath(_MODULE_DIR_ . 'tpay/tpayLibs/src/');
        try {
            if ($paymentType === TPAY_PAYMENT_BASIC || $paymentType === TPAY_PAYMENT_INSTALLMENTS) {
                $this->renderBasic();
            } elseif ($paymentType === TPAY_PAYMENT_CARDS) {
                $this->renderCard();
            } elseif ($paymentType === TPAY_PAYMENT_BLIK) {
                $this->renderBlik();
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * @param $cart
     * @param $errorRedirectLink
     * @param $customer
     * @param $paymentType
     */
    private function checkForErrors($cart, $errorRedirectLink, $customer, $paymentType)
    {
        if (
            $cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0
            || !$this->module->active
        ) {
            Tools::redirect($errorRedirectLink);
        }

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect($errorRedirectLink);
        }

        if (!in_array($paymentType, array(
            TPAY_PAYMENT_BASIC,
            TPAY_PAYMENT_INSTALLMENTS,
            TPAY_PAYMENT_CARDS,
            TPAY_PAYMENT_BLIK,
        ))
        ) {
            Tools::redirect($errorRedirectLink);
        }

        $this->isPaymentMethodActive($paymentType, $errorRedirectLink);
    }

    /**
     * @param $paymentType
     * @param $errorRedirectLink
     */
    private function isPaymentMethodActive($paymentType, $errorRedirectLink)
    {
        if (($paymentType === TPAY_PAYMENT_BASIC) && (int)Configuration::get('TPAY_BASIC_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
        if (($paymentType === TPAY_PAYMENT_BLIK) && (int)Configuration::get('TPAY_BLIK_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
        if (($paymentType === TPAY_PAYMENT_CARDS) && (int)Configuration::get('TPAY_CARD_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
        if (($paymentType === TPAY_PAYMENT_INSTALLMENTS) && (int)Configuration::get('TPAY_INSTALLMENTS_ACTIVE') !== 1) {
            Tools::redirect($errorRedirectLink);
        }
    }

    /**
     * @param $orderTotal
     */
    private function setOrderTotal($orderTotal)
    {
        $surcharge = TpayHelperClient::getSurchargeValue($orderTotal);
        if (!empty($surcharge)) {
            $orderTotal += $surcharge;
            $this->context->smarty->assign(array(
                'surcharge' => number_format(str_replace(array(',', ' '), array('.', ''), $surcharge),
                    $this->displayPrecision, '.', ''),
            ));
        }
        $this->context->smarty->assign(array(
            'orderTotal' => number_format(str_replace(array(',', ' '), array('.', ''), $orderTotal),
                $this->displayPrecision, '.', ''),
        ));
    }

    private function renderBasic()
    {
        $paymentViewType = (int)Configuration::get('TPAY_BANK_ON_SHOP');
        $showRegulations = (bool)(int)Configuration::get('TPAY_SHOW_REGULATIONS');
        $this->context->smarty->assign(array('showRegulations' => $showRegulations));
        $this->setTpayTemplate();
        if ($this->installments || $paymentViewType === TPAY_VIEW_REDIRECT) {
            $form = $this->getRedirectionForm($showRegulations);
        } else {
            $form = $this->getBankForm($paymentViewType === TPAY_VIEW_LIST, $showRegulations);
        }
        $this->assignSmartyData($form, 'payment.tpl');
    }

    private function setTpayTemplate()
    {
        TPAY_PS_17 ? $this->setTemplate(TPAY_17_PATH . '/payment17.tpl') : $this->setTemplate('payment.tpl');
    }

    private function getRedirectionForm($showRegulations)
    {
        $formProvider = TpayHelperClient::getBasicClient();
        return $formProvider->getTransactionForm([], false,
            $this->context->link->getModuleLink('tpay', 'payment?type=' . TPAY_PAYMENT_INSTALLMENTS), true,
            $showRegulations);
    }

    private function getBankForm($smallList = false, $regulations = true)
    {
        $formProvider = TpayHelperClient::getBasicClient();
        return $formProvider->getBankSelectionForm([], $smallList, $regulations,
            $this->context->link->getModuleLink('tpay', 'payment?type=' . TPAY_PAYMENT_BASIC));
    }

    private function assignSmartyData($paymentForm, $nextTpl = null)
    {
        $blikOn = (bool)(int)Configuration::get('TPAY_BLIK_ACTIVE');
        $showSummary = (bool)(int)Configuration::get('TPAY_SUMMARY');
        $paymentConfig['merchant_id'] = (int)Configuration::get('TPAY_ID');
        $paymentConfig['regulation_url'] = 'https://secure.tpay.com/partner/pliki/regulamin.pdf';
        $cart = $this->context->cart;
        $tplDir = TPAY_PS_17 ? _PS_MODULE_DIR_ . 'tpay/views/templates/front/' :
            _PS_MODULE_DIR_ . 'tpay/views/templates/front';
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
                        number_format(str_replace(array(',', ' '), array('.', ''), $value), $this->displayPrecision,
                            '.', '') : $value;
                }
            }
            ksort($orderProductsDetails[$i]);
        }
        $addressDeliveryId = $cart->id_address_delivery;
        $addressInvoiceId = $cart->id_address_invoice;
        $InvAddress = new AddressCore($addressInvoiceId);
        $deliveryAddress = new AddressCore($addressDeliveryId);

        $this->context->smarty->assign(array(
            'paymentConfig'    => $paymentConfig,
            'showSummary'      => $showSummary,
            'blikOn'           => $blikOn,
            'productsT'        => $orderProductsDetails,
            'shippingCostT'    => $cart->getTotalShippingCost(),
            'invAddressT'      => $InvAddress,
            'deliveryAddressT' => $deliveryAddress,
            'installments'     => $this->installments,
            'tplDir'           => $tplDir,
            'nextTpl'          => $nextTpl,
            'paymentForm'      => $paymentForm,
        ));
        $this->assignTemplatesPatches();
    }

    private function assignTemplatesPatches()
    {
        $tplDir = 'modules/tpay/views/templates/front/';
        $templates = array(
            'orderSummary',
        );
        foreach ($templates as $key) {
            $this->context->smarty->assign(array(
                $key . 'Path' => Tools::file_exists_cache(_PS_THEME_DIR_ . $tplDir . $key . '.tpl') ?
                    _PS_THEME_DIR_ . $tplDir . $key . '.tpl' : _PS_ROOT_DIR_ . '/' . $tplDir . $key . '.tpl',
            ));
        }
    }

    private function renderCard()
    {
        $midId = TpayHelperClient::getCardMidNumber($this->context->currency->iso_code,
            _PS_BASE_URL_ . __PS_BASE_URI__);
        $paymentCard = TpayHelperClient::getCardClient($midId);
        $language = $this->context->language->iso_code;
        if ($language !== 'pl') {
            $language = 'en';
        }
        (new Util)->setLanguage($language)->setPath(__PS_BASE_URI__ . 'modules/tpay/tpayLibs/src/');
        $form = $paymentCard->getOnSiteCardForm($this->context->link->getModuleLink('tpay',
            'payment?type=' . TPAY_PAYMENT_CARDS), false);
        $this->setTpayTemplate();
        $this->assignSmartyData($form, 'payment.tpl');
    }

    private function renderBlik()
    {
        $this->setTpayTemplate();
        $form = $this->getBlikForm();
        $this->assignSmartyData($form, 'payment.tpl');
    }

    private function getBlikForm()
    {
        $formProvider = TpayHelperClient::getBasicClient();
        return $formProvider->getBlikBasicForm($this->context->link->getModuleLink('tpay',
            'payment?type=' . TPAY_PAYMENT_BASIC));
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
