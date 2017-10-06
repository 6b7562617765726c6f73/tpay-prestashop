<?php
/**
 * NOTICE OF LICENSE
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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/tpayModel.php';
require_once dirname(__FILE__) . '/helpers/TpayHelperClient.php';
require_once dirname(__FILE__) . '/ConfigFieldsDef/ConfigFieldsNames.php';

define('TPAY_PAYMENT_BASIC', 'basic');
define('TPAY_PAYMENT_CARDS', 'cards');
define('TPAY_PAYMENT_BANK_ON_SHOP', 'bank');
define('TPAY_PAYMENT_BLIK', 'blik');
define('TPAY_PAYMENT_INSTALLMENTS', 'installments');
define('TPAY_VIEW_REDIRECT', 0);
define('TPAY_VIEW_ICONS', 1);
define('TPAY_VIEW_LIST', 2);
define('TPAY_CARD_MIDS', 11);
define('TPAY_SURCHARGE_AMOUNT', 0);
define('TPAY_SURCHARGE_PERCENT', 1);

define('TPAY_CARD_CURRENCIES', serialize(array(
    'PLN' => '985',
    'GBP' => '826',
    'USD' => '840',
    'EUR' => '978',
    'CZK' => '203',
)));

/**
 * Class Tpay main class.
 */
class Tpay extends PaymentModule
{
    const LOGO_PATH = 'tpay/views/img/tpay_logo.png';
    const YES = 1;
    const NO = 0;
    const IS_ACTIVE = 1;
    const NOT_ACTIVE = 0;
    const BASE_INPUT_SIZE = 50;
    const BASIC_ACTIVE = 'TPAY_BASIC_ACTIVE';
    const BLIK_ACTIVE = 'TPAY_BLIK_ACTIVE';
    const LOGO_ACTIVE = 'TPAY_LOGO_ACTIVE';
    const DEBUG = 'TPAY_DEBUG';
    const CHECK_IP = 'TPAY_CHECK_IP';
    const GOOGLE_ID = 'TPAY_GOOGLE_ID';
    const BANK_ON_SHOP = 'TPAY_BANK_ON_SHOP';
    const SHOW_REGULATIONS = 'TPAY_SHOW_REGULATIONS';
    const TPAY_KEY = 'TPAY_KEY';
    const TPAY_ID = 'TPAY_ID';
    const TPAY_BLIK_APIKEY = 'TPAY_BLIK_APIKEY';
    const TPAY_BLIK_APIPASS = 'TPAY_BLIK_APIPASS';
    const SURCHARGE_ACTIVE = 'TPAY_SURCHARGE_ACTIVE';
    const SURCHARGE_TYPE = 'TPAY_SURCHARGE_TYPE';
    const SURCHARGE_VALUE = 'TPAY_SURCHARGE_VALUE';
    const CHECK_PROXY = 'TPAY_CHECK_PROXY';
    const SUBMIT = 'submit';

    /**
     * Basic moudle info.
     */
    public function __construct()
    {
        $this->name = 'tpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.4.0';
        $this->author = 'Krajowy Integrator Płatności S.A.';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->is_eu_compatible = 1;

        parent::__construct();

        $this->displayName = $this->l('tpay payments');
        $this->description = $this->l('Module payments');

        $this->confirmUninstall = $this->l('Delete this module?');
        $this->module_key = 'f2eb0ce26233d0b517ba41e81f2e62fe';
    }

    /**
     * Module installation.
     *
     * @return bool
     */
    public function install()
    {
        /*
         * check multishop context
         */
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (version_compare(phpversion(), '5.3.0', '<')) {
            $this->_errors[] = $this->l(
                sprintf(
                    'Your PHP version is too old, please upgrade to a newer version. Your version is %s,' .
                    ' library requires %s',
                    phpversion(),
                    '5.3.0'
                )
            );

            return false;
        }

        if (!parent::install()) {
            $this->_errors[] = $this->l('Initialization failed');
        }

        if (!$this->registerHook('payment')) {
            $this->_errors[] = $this->l('Error adding payment methods');
        }

        if (!TpayModel::createTable()) {
            $this->_errors[] = $this->l('Error creating table');
        }

        if (!$this->addOrderStates()) {
            $this->_errors[] = $this->l('Error adding order statuses');
        }

        if (!$this->registerHook('displayProductButtons')) {
            $this->_errors[] = $this->l('Error adding tpay logo');
        }

        if (!$this->registerHook('displayPaymentEU')) {
            $this->_errors[] = $this->l('Error adding EU payment');
        }
        if (!empty($this->_errors)) {
            return false;
        }

        Configuration::updateValue('TPAY_CHECK_IP', 1);
        Configuration::updateValue('TPAY_CHECK_PROXY', 0);

        return true;
    }

    /**
     * Add order states.
     *
     * @return bool
     */
    private function addOrderStates()
    {
        $newState = Configuration::get('TPAY_NEW');
        if (
            !$newState
            ||
            empty($newState)
            ||
            !Validate::isInt($newState)
            ||
            !Validate::isLoadedObject(new OrderState($newState))
        ) {
            $orderState = new OrderState();

            $orderState->name = array_fill(0, 10, 'Waiting for payment (tpay)');
            $orderState->send_email = 0;
            $orderState->invoice = 0;
            $orderState->color = '#5bc0de';
            $orderState->unremovable = false;
            $orderState->logable = 0;
            $orderState->module_name = $this->name;
            if (!$orderState->add()) {
                return false;
            }

            if (!Configuration::updateValue('TPAY_NEW', $orderState->id)) {
                return false;
            }

            try {
                copy(_PS_MODULE_DIR_ . 'tpay/views/img/logo.gif', _PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif');
            } catch (Exception $e) {
                Tools::displayError(
                    $this->l(
                        'Copying image failed. Please copy ' .
                        'logo.gif to directory img/os and change the name to ' . $orderState->id . '.gif'
                    )
                );
            }
        }

        $doneState = Configuration::get('TPAY_CONFIRMED');
        if (
            !$doneState
            ||
            empty($doneState)
            ||
            !Validate::isInt($doneState)
            ||
            !Validate::isLoadedObject(new OrderState($doneState))
        ) {
            $orderState = new OrderState();

            $orderState->name = array_fill(0, 10, 'Payment received (tpay)');
            $orderState->send_email = false;
            $orderState->invoice = true;
            $orderState->color = '#00DE69';
            $orderState->unremovable = false;
            $orderState->logable = true;
            $orderState->module_name = $this->name;
            $orderState->paid = true;
            $orderState->pdf_invoice = true;
            $orderState->pdf_delivery = true;
            if (!$orderState->add()) {
                return false;
            }

            if (!Configuration::updateValue('TPAY_CONFIRMED', $orderState->id)) {
                return false;
            }

            try {
                copy(_PS_MODULE_DIR_ . 'tpay/views/img/done.gif', _PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif');
            } catch (Exception $e) {
                Tools::displayError(
                    $this->l(
                        'Copying image failed. Please copy ' .
                        'done.gif to directory img/os and change the name to ' . $orderState->id . '.gif'
                    )
                );
            }
        }

        $errorState = Configuration::get('TPAY_ERROR');
        if (
            !$errorState
            ||
            empty($errorState)
            ||
            !Validate::isInt($errorState)
            ||
            !Validate::isLoadedObject(new OrderState($errorState))
        ) {
            $orderState = new OrderState();

            $orderState->name = array_fill(0, 10, 'Wrong payment (tpay)');
            $orderState->send_email = false;
            $orderState->invoice = false;
            $orderState->color = '#b52b27';
            $orderState->unremovable = false;
            $orderState->logable = false;
            $orderState->module_name = $this->name;
            $orderState->paid = false;
            $orderState->pdf_invoice = false;
            $orderState->pdf_delivery = false;
            if (!$orderState->add()) {
                return false;
            }

            if (!Configuration::updateValue('TPAY_ERROR', $orderState->id)) {
                return false;
            }

            try {
                copy(_PS_MODULE_DIR_ . 'tpay/views/img/error.gif', _PS_IMG_DIR_ . 'os/' . $orderState->id . '.gif');
            } catch (Exception $e) {
                Tools::displayError(
                    $this->l(
                        'Copying image failed. Please copy ' .
                        'error.gif to directory img/os and change the name to ' . $orderState->id . '.gif'
                    )
                );
            }
        }

        return true;
    }

    /**
     * Module uninstall.
     *
     * @return bool
     */
    public function uninstall()
    {
        if (!parent::uninstall() ||
            !Configuration::deleteByName('tpay')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Admin config settings check an render form.
     */
    public function getContent()
    {
        $output = null;
        $errors = false;

        if (Tools::isSubmit('submit' . $this->name)) {
            $basicActive = (int)Tools::getValue('TPAY_BASIC_ACTIVE');
            $blikActive = (int)Tools::getValue('TPAY_BLIK_ACTIVE');
            $bannerActive = (int)Tools::getValue('TPAY_BANNER');
            $summaryActive = (int)Tools::getValue('TPAY_SUMMARY');
            $installmentActive = (int)Tools::getValue('TPAY_INSTALLMENTS_ACTIVE');
            $userDefinedStatusesActive = (int)Tools::getValue('TPAY_OWN_STATUS');
            Configuration::updateValue('TPAY_INSTALLMENTS_ACTIVE', $installmentActive);
            Configuration::updateValue('TPAY_BASIC_ACTIVE', $basicActive);
            Configuration::updateValue('TPAY_BLIK_ACTIVE', $blikActive);
            Configuration::updateValue('TPAY_BANNER', $bannerActive);
            Configuration::updateValue('TPAY_SUMMARY', $summaryActive);
            Configuration::updateValue('TPAY_OWN_STATUS', $userDefinedStatusesActive);
            /**
             * debug option.
             */
            $debug = (int)Tools::getValue('TPAY_DEBUG');
            Configuration::updateValue('TPAY_DEBUG', $debug);
            $debug = (int)Tools::getValue('TPAY_CARD_DEBUG');
            Configuration::updateValue('TPAY_CARD_DEBUG', $debug);
            /**
             * Notifications options.
             */
            $checkIp = (int)Tools::getValue('TPAY_CHECK_IP');
            Configuration::updateValue('TPAY_CHECK_IP', $checkIp);
            $checkProxy = (int)Tools::getValue(static::CHECK_PROXY);
            Configuration::updateValue(static::CHECK_PROXY, $checkProxy);
            /**
             * google analytics.
             */
            $googleID = Tools::getValue('TPAY_GOOGLE_ID');
            if (!Validate::isTrackingNumber($googleID)) {
                if (!empty($googleID)) {
                    $output .= $this->displayError($this->l('Invalid google id'));
                }
            }
            Configuration::updateValue('TPAY_GOOGLE_ID', $googleID);

            /**
             * Basic payment settings validation.
             */
            $bankOnShop = (int)Tools::getValue('TPAY_BANK_ON_SHOP');
            Configuration::updateValue('TPAY_BANK_ON_SHOP', $bankOnShop);
            $regulations = (int)Tools::getValue('TPAY_SHOW_REGULATIONS');
            Configuration::updateValue('TPAY_SHOW_REGULATIONS', $regulations);
            $blikOnShop = (int)Tools::getValue('TPAY_SHOW_BLIK_ON_SHOP');
            Configuration::updateValue('TPAY_SHOW_BLIK_ON_SHOP', $blikOnShop);

            $localError = false;
            if ($basicActive === 1) {
                if (
                    $bankOnShop !== TPAY_VIEW_REDIRECT
                    &&
                    $bankOnShop !== TPAY_VIEW_ICONS
                    &&
                    $bankOnShop !== TPAY_VIEW_LIST
                ) {
                    $output .= $this->displayError(
                        $this->l(
                            'Invalid value "View for payment channels"'
                        )
                    );
                    $localError = true;
                }
                if ($regulations !== 0 && $regulations !== 1) {
                    $output .= $this->displayError(
                        $this->l(
                            'Invalid value for "tpay regulations view on the seller webiste"'
                        )
                    );
                    $localError = true;
                }

                if ($localError !== false) {
                    Configuration::updateValue('TPAY_BASIC_ACTIVE', 0);
                }
            }

            Configuration::updateValue('TPAY_OWN_WAITING', Tools::getValue('TPAY_OWN_WAITING'));
            Configuration::updateValue('TPAY_OWN_ERROR', Tools::getValue('TPAY_OWN_ERROR'));
            Configuration::updateValue('TPAY_OWN_PAID', Tools::getValue('TPAY_OWN_PAID'));
            /**
             * basic settings validation.
             */
            $userKey = (string)Tools::getValue('TPAY_KEY');
            $userId = Tools::getValue('TPAY_ID');
            $apiKey = (string)Tools::getValue('TPAY_APIKEY');
            $apiPass = Tools::getValue('TPAY_APIPASS');
            $cardsActive = Tools::getValue('TPAY_CARD_ACTIVE');
            for ($i = 1; $i < TPAY_CARD_MIDS; $i++) {
                foreach (ConfigFieldsNames::getCardConfigFields() as $key) {
                    Configuration::updateValue($key . $i, Tools::getValue($key . $i));
                }
            }

            Configuration::updateValue('TPAY_KEY', $userKey);
            Configuration::updateValue('TPAY_ID', $userId);
            Configuration::updateValue('TPAY_APIKEY', $apiKey);
            Configuration::updateValue('TPAY_APIPASS', $apiPass);
            Configuration::updateValue('TPAY_CARD_ACTIVE', $cardsActive);


            if (
                $basicActive === 1
                ||
                $blikActive === 1
            ) {
                if (!$userKey || empty($userKey) || !Validate::isGenericName($userKey)) {
                    $output .= $this->displayError($this->l('Invalid security code'));
                    $errors = true;
                }

                if (!$userId || empty($userId) || !Validate::isInt($userId)) {
                    $output .= $this->displayError($this->l('Invalid user id'));
                    $errors = true;
                }

                if ($errors !== false) {
                    Configuration::updateValue('TPAY_BASIC_ACTIVE', 0);
                    Configuration::updateValue('TPAY_BLIK_ACTIVE', 0);
                }
            }

            $surchargeActive = (int)Tools::getValue('TPAY_SURCHARGE_ACTIVE');
            $surchargeType = (int)Tools::getValue('TPAY_SURCHARGE_TYPE');
            $surchargeValue = Tools::getValue('TPAY_SURCHARGE_VALUE');
            $surchargeValue = str_ireplace(',', '.', $surchargeValue);
            $surchargeValue = round((float)$surchargeValue, 2);

            Configuration::updateValue('TPAY_SURCHARGE_ACTIVE', $surchargeActive);
            Configuration::updateValue('TPAY_SURCHARGE_TYPE', $surchargeType);

            if ($surchargeActive === 1) {
                if (!Validate::isUnsignedFloat($surchargeValue)) {
                    $this->displayError($this->l('Invalid payment value'));
                    Configuration::updateValue('TPAY_SURCHARGE_ACTIVE', 0);
                } else {
                    Configuration::updateValue('TPAY_SURCHARGE_VALUE', $surchargeValue);
                }
            }

            $output .= $this->displayConfirmation($this->l('Settings saved'));
        }
        include_once(dirname(__FILE__) . '/views/templates/admin/configuration.tpl');
        return $output . $this->displayForm();
    }

    /**
     * Configuration form settings.
     *
     * @return mixed
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $helper = new HelperForm();

        // Init payment configuration form
        $fields_form = $this->prepareConfigFormArrays();
        // Load current values
        $helper->fields_value = $this->getConfigFieldsValues();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ),
        );

        return $helper->generateForm($fields_form);
    }

    /**
     * Prepare config forms array.
     *
     * @return array
     */
    private function prepareConfigFormArrays()
    {
        if ((float)_PS_VERSION_ >= 1.6) {
            $switch = 'switch';
        } else {
            $switch = 'radio';
        }
        $orderStatesData = OrderState::getOrderStates(1);
        $orderStates = array();
        foreach ($orderStatesData as $state) {
            array_push($orderStates, array(
                'id_option' => $state['id_order_state'],
                'name'      => $state['name']
            ));
        }

        $generalSettings = require_once dirname(__FILE__) . '/ConfigFieldsDef/GeneralSettingsDefinition.php';
        $basicPayment = require_once dirname(__FILE__) . '/ConfigFieldsDef/BasicPaymentDefinition.php';
        $blikPayment = require_once dirname(__FILE__) . '/ConfigFieldsDef/BlikPaymentDefinition.php';
        $cardPayment = require_once dirname(__FILE__) . '/ConfigFieldsDef/CardPaymentDefinition.php';

        return array($generalSettings, $basicPayment, $blikPayment, $cardPayment);
    }

    /**
     * Returns config fields array.
     *
     * @return array
     */
    private function getConfigFieldsValues()
    {
        $config = array();
        foreach (ConfigFieldsNames::getConfigFields() as $key) {
            $config[$key] = Configuration::get($key);
        }
        for ($i = 1; $i < TPAY_CARD_MIDS; $i++) {
            foreach (ConfigFieldsNames::getCardConfigFields() as $key) {
                $config[$key . $i] = Configuration::get($key . $i);
            }
        }
        return $config;
    }

    /**
     * @param $params
     *
     * @return bool
     */
    public function hookDisplayPaymentEU($params)
    {
        $this->context->controller->addCss($this->_path . 'views/css/style.css');

        if (!$this->active) {
            return false;
        }

        return $this->hookPayment(true);
    }

    /**
     * Render payment choice blocks.
     *
     * @param bool $returnPayments
     *
     * @return array
     */
    public function hookPayment($returnPayments = false)
    {
        $currency = $this->context->currency;

        $this->context->controller->addCss($this->_path . 'views/css/style.css');

        if (!$this->active) {
            return false;
        }

        $availablePayments = array();
        $cart = $this->context->cart;
        $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
        $basicActive = (int)Configuration::get('TPAY_BASIC_ACTIVE');
        $cardActive = (int)Configuration::get('TPAY_CARD_ACTIVE');
        $installmentsActive = (int)Configuration::get('TPAY_INSTALLMENTS_ACTIVE');
        if ($basicActive === 1 && $currency->iso_code === 'PLN') {

            $paymentLink = $this->context->link->getModuleLink(
                'tpay',
                'validation',
                array('type' => TPAY_PAYMENT_BASIC)
            );
            $availablePayments[] = array(
                'type'        => TPAY_PAYMENT_BASIC,
                'paymentLink' => $paymentLink,
                'title'       => $this->l('Pay by online transfer with tpay.com'),
                'cta_text'    => $this->l('Pay by online transfer with tpay.com'),
                'logo'        => _MODULE_DIR_ . 'tpay/views/img/logo.png',
                'action'      => $this->context->link->getModuleLink(
                    $this->name,
                    'validation',
                    array('type' => TPAY_PAYMENT_BASIC),
                    true
                ),
            );
            if ($installmentsActive === 1 && $orderTotal >= 300) {
                $paymentLink = $this->context->link->getModuleLink(
                    'tpay',
                    'validation',
                    array('type' => TPAY_PAYMENT_INSTALLMENTS)
                );
                $availablePayments[] = array(
                    'type'        => TPAY_PAYMENT_INSTALLMENTS,
                    'paymentLink' => $paymentLink,
                    'title'       => $this->l('Pay by installments with tpay'),
                    'cta_text'    => $this->l('Pay by installments with tpay'),
                    'logo'        => _MODULE_DIR_ . 'tpay/views/img/logo.png',
                    'action'      => $this->context->link->getModuleLink(
                        $this->name,
                        'validation',
                        array('type' => TPAY_PAYMENT_INSTALLMENTS),
                        true
                    ),
                );
            }
        }
        if ($cardActive === 1 && TpayHelperClient::getCardMidNumber($currency->iso_code,
                _PS_BASE_URL_ . __PS_BASE_URI__)
        ) {
            $paymentLink = $this->context->link->getModuleLink(
                'tpay',
                'validation',
                array('type' => TPAY_PAYMENT_CARDS)
            );
            $availablePayments[] = array(
                'type'        => TPAY_PAYMENT_CARDS,
                'paymentLink' => $paymentLink,
                'title'       => $this->l('Pay by credit card with tpay.com'),
                'cta_text'    => $this->l('Pay by credit card with tpay.com'),
                'logo'        => _MODULE_DIR_ . 'tpay/views/img/logo.png',
                'action'      => $this->context->link->getModuleLink(
                    $this->name,
                    'validation',
                    array('type' => TPAY_PAYMENT_CARDS),
                    true
                ),
            );
        }

        if ($returnPayments === true) {
            return $availablePayments;
        }

        $this->smarty->assign(array(
            'this_path'     => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'payments'      => $availablePayments,
        ));


        $surcharge = TpayHelperClient::getSurchargeValue($orderTotal);
        if (!empty($surcharge)) {
            $this->smarty->assign('surcharge', $surcharge);
        }

        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Hook for displaying tpay logo on product pages.
     *
     * @param $params
     */
    public function hookDisplayProductButtons($params)
    {
        if (Configuration::get('PS_CATALOG_MODE') || Configuration::get('TPAY_BANNER') == false) {
            return;
        }
        if (!$this->isCached('paymentlogo.tpl', $this->getCacheId())) {
            $this->smarty->assign(array(
                'banner_img' => 'https://tpay.com/img/banners/tpay-160x75.png',
            ));
        }

        return $this->display(__FILE__, 'paymentlogo.tpl', $this->getCacheId());
    }

}
