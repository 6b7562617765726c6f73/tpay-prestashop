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

use tpayLibs\src\_class_tpay\Utilities\TException;
use tpayLibs\src\_class_tpay\Utilities\Util;
use tpayLibs\src\Dictionaries\FieldsConfigDictionary;

require_once _PS_MODULE_DIR_ . 'tpay/helpers/TpayHelperClient.php';
require_once _PS_MODULE_DIR_ . 'tpay/helpers/TpayOrderStatusHandler.php';
require_once _PS_MODULE_DIR_ . 'tpay/tpayModel.php';

/**
 * Class TpayPaymentModuleFrontController.
 */
class TpayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    const TPAY_URL = 'https://secure.tpay.com';

    private $tpayClientConfig;

    private $tpayPaymentId;

    private $midId = 11;

    /**
     * @var TpayOrderStatusHandler
     */
    private $statusHandler;

    public function initContent()
    {
        $this->display_column_left = false;
        parent::initContent();

        $this->statusHandler = new TpayOrderStatusHandler();
        $cart = $this->context->cart;
        if (empty($cart->id)) {
            exit('Cart Id is empty!');
        }
        $currency = $this->context->currency;
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
        $crc_sum = md5($cart->id . $this->context->cookie->mail . $customer->secure_key . time());
        $surcharge = TpayHelperClient::getSurchargeValue($orderTotal);
        if (is_float($surcharge)) {
            $feeProductId = TpayHelperClient::getTpayFeeProductId();
            $feeProduct = new Product($feeProductId, true);
            $feeProduct->price = $surcharge;
            $saveResult = $feeProduct->save();
            Util::logLine(sprintf('CartId %s', $cart->id));
            Util::logLine(sprintf('Calculated surcharge value %s. Saving Tpay fee price result %s',
                $surcharge, $saveResult));
            $feeProduct->flushPriceCache();
            if (!$cart->containsProduct($feeProductId)) {
                Util::logLine('Cart does not contain fee product.');
                $updateQtyResult = $cart->updateQty(1, $feeProductId);
                $updateCartResult = $cart->update();
                Util::logLine(sprintf('Update qty result %s', $updateQtyResult));
                Util::logLine(sprintf('Update cart result %s', $updateCartResult));
                $cart->getPackageList(true);
                $orderTotal += $surcharge;
            } else {
                Util::logLine('Cart already contain fee product.');
            }
        } else {
            Util::logLine(sprintf('No surcharge for this order. Surcharge value: %s', $surcharge));
            $surcharge = 0.0;
        }
        if ($cart->OrderExists() === true) {
            die($this->trans('Cart cannot be loaded or an order has already been placed using this cart', array(),
                'Admin.Payment.Notification'));
        }
        $this->context->smarty->assign(array(
            'nbProducts' => $cart->nbProducts(),
        ));
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
        $orderId = $this->module->currentOrder;
        Util::logLine(sprintf('OrderId %s', $orderId));
        $this->tpayClientConfig['amount'] = number_format(str_replace(array(',', ' '), array('.', ''),
            $orderTotal), 2, '.', '');
        $this->tpayClientConfig['crc'] = $crc_sum;
        $type = Tools::getValue('type');
        $installments = $type === TPAY_PAYMENT_INSTALLMENTS;
        $paymentType = $installments || $type === TPAY_PAYMENT_BASIC ? 'basic' : 'card';
        /*
         * Insert order to db
         */
        $this->midId = TpayHelperClient::getCardMidNumber($this->context->currency->iso_code,
            _PS_BASE_URL_ . __PS_BASE_URI__);
        TpayModel::insertOrder($orderId, $crc_sum, $paymentType, false, $surcharge, $this->midId);
        $this->initBasicClient($installments, $cart, $customer);
        $this->context->cookie->last_order = $orderId;
        unset($this->context->cookie->id_cart);
        if (Tools::getValue('type') === TPAY_PAYMENT_CARDS) {
            $this->processCardPayment($orderId);
        } else {
            $this->redirectToPayment();
        }
    }

    private function initBasicClient($installments, $cart, $customer)
    {
        $baseUrl = Tools::getHttpHost(true) . __PS_BASE_URI__;
        $addressInvoiceId = $cart->id_address_invoice;
        $billingAddress = new AddressCore($addressInvoiceId);
        $order = new Order($this->module->currentOrder);
        $reference = $order->reference;
        $this->tpayClientConfig += array(
            'description' => 'Zamówienie ' . $reference . '. Klient ' .
                $this->context->cookie->customer_firstname . ' ' . $this->context->cookie->customer_lastname,
            'return_url'      => $baseUrl . 'index.php?controller=order-confirmation&id_cart=' .
                (int)$cart->id.'&id_module=' . (int)$this->module->id . '&id_order=' . $this->module->currentOrder .
                '&key='.$customer->secure_key . '&status=success',
            'return_error_url' => $this->context->link->getModuleLink('tpay', 'order-error').
                '?orderId='.$this->module->currentOrder,
            'email' => $this->context->cookie->email,
            'name' => sprintf('%s %s', $billingAddress->firstname, $billingAddress->lastname),
            'phone' => $billingAddress->phone,
            'address' => $billingAddress->address1,
            'city' => $billingAddress->city,
            'zip' => $billingAddress->postcode,
            'result_url' => $this->context->link->getModuleLink('tpay', 'confirmation',
                array('type' => TPAY_PAYMENT_BASIC)),
            'module' => 'prestashop ' . _PS_VERSION_,
        );
        if ((int)Tools::getValue('regulations') === 1 || (int)Tools::getValue('accept_tos') === 1 || $installments) {
            $this->tpayClientConfig['accept_tos'] = 1;
        }
        if (!empty(Configuration::get('TPAY_NOTIFICATION_EMAILS'))) {
            $this->tpayClientConfig += array('result_email' => Configuration::get('TPAY_NOTIFICATION_EMAILS'));
        }
        if ((int)Tools::getValue('group') > 0) {
            $this->tpayClientConfig += array('group' => (int)Tools::getValue('group'));
        }
        if ($installments) {
            $this->tpayClientConfig['group'] = 109;
        }
        foreach ($this->tpayClientConfig as $key => $value) {
            if (empty($value)) {
                unset($this->tpayClientConfig[$key]);
            }
        }
        Util::log('Tpay order parameters', print_r($this->tpayClientConfig, true));
    }

    private function processCardPayment($orderId)
    {
        $tpayCardClient = TpayHelperClient::getCardClient($this->midId);

        $cardData = Util::post('carddata', FieldsConfigDictionary::STRING);
        $clientName = $this->tpayClientConfig['name'];
        $clientEmail = $this->tpayClientConfig['email'];
        $saveCard = Util::post('card_save', FieldsConfigDictionary::STRING);
        Util::log('Secure Sale post params', print_r($_POST, true));
        if ($saveCard === 'on') {
            $tpayCardClient->setOneTimer(false);
        }

        $tpayCardClient->setAmount($this->tpayClientConfig['amount'])
            ->setCurrency($this->context->currency->iso_code_num)
            ->setOrderID($this->midId . '*tpay*' . $this->tpayClientConfig['crc']);
        $tpayCardClient->setLanguage($this->context->language->iso_code)
            ->setReturnUrls($this->tpayClientConfig['return_url'], $this->tpayClientConfig['return_url'])
            ->setModuleName('prestashop ' . _PS_VERSION_);
        $response = $tpayCardClient->registerSale($clientName, $clientEmail, $this->tpayClientConfig['description'],
            $cardData);

        if (isset($response['result']) && (int)$response['result'] === 1) {
            $tpayCardClient->setAmount($this->tpayClientConfig['amount'])->setOrderID('')
                ->validateCardSign($response['sign'], $response['sale_auth'], $response['card'],
                    $response['date'], 'correct', isset($response['test_mode']) ? '1' : '', '', '');
            $this->tpayPaymentId = $response['sale_auth'];
            $this->statusHandler->setOrdersAsConfirmed($orderId, $this->tpayPaymentId, false);
            Tools::redirect($this->tpayClientConfig['return_url']);

        } elseif (isset($response['3ds_url'])) {
            Tools::redirect($response['3ds_url']);
        } else {
            $this->statusHandler->setOrdersAsConfirmed($orderId, $this->tpayPaymentId, true);
            if ((int)Configuration::get('TPAY_CARD_DEBUG') === 1) {
                var_dump($response);
            } else {
                Tools::redirect($this->tpayClientConfig['return_error_url']);
            }
        }
    }

    private function redirectToPayment()
    {
        if (Tools::getValue('blik_code') && (is_int((int)(Tools::getValue('blik_code'))))) {
            $this->processBlikPayment($this->tpayClientConfig);
        } else {
            $tpayBasicClient = TpayHelperClient::getBasicClient();
            $language = $this->context->language->iso_code;
            if ($language !== 'pl') {
                $language = 'en';
            }
            (new Util)->setLanguage($language);
            if (TPAY_PS_17) {
                $this->setTemplate(TPAY_17_PATH . '/redirect.tpl');
                echo $tpayBasicClient->getTransactionForm($this->tpayClientConfig, true);
            } else {
                $this->setTemplate('tpayRedirect.tpl');
                $this->context->smarty->assign(array(
                    'tpay_form' => $tpayBasicClient->getTransactionForm($this->tpayClientConfig, true),
                    'tpay_path' => _MODULE_DIR_ . 'tpay/views',
                    'HOOK_HEADER' => Hook::exec('displayHeader'),
                ));
            }
        }
    }

    private function processBlikPayment($data)
    {
        $data['group'] = 150;
        $data['accept_tos'] = 1;
        $errorUrl = $data['return_error_url'];
        $tpayApiClient = TpayHelperClient::getApiClient();
        try {
            $resp = $tpayApiClient->create($data);
            if ((int)$resp['result'] === 1) {
                $blikData['code'] = Tools::getValue('blik_code');
                $blikData['title'] = $resp['title'];
                $respBlik = $tpayApiClient->handleBlikPayment($blikData);

                if ($respBlik) {
                    Tools::redirect($data['return_url']);
                } else {
                    Tools::redirect($resp['url']);
                }
            } else {
                Tools::redirect($errorUrl);
            }
        } catch (TException $e) {
            Tools::redirect($errorUrl);
        }
    }

}
