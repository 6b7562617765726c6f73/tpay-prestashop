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

define('TPAY_PAYMENT_BASIC', 'basic');
define('TPAY_PAYMENT_BANK_ON_SHOP', 'bank');
define('TPAY_PAYMENT_BLIK', 'blik');
define('TPAY_PAYMENT_INSTALLMENTS', 'installments');
define('TPAY_VIEW_REDIRECT', 0);
define('TPAY_VIEW_ICONS', 1);
define('TPAY_VIEW_LIST', 2);

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
    /**
     * Basic moudle info.
     */
    public function __construct()
    {
        $this->name = 'tpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.2.2';
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
//        if (!Configuration::get('tpay'))
//            $this->warning = $this->l('No name provided');
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

        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->_errors[] = $this->l(
                sprintf(
                    'Your PHP version is too old, please upgrade to a newer version. Your version is %s,' .
                    ' library requires %s',
                    phpversion(),
                    '5.4.0'
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
            $installmentActive = (int)Tools::getValue('TPAY_INSTALLMENTS_ACTIVE');
            Configuration::updateValue('TPAY_INSTALLMENTS_ACTIVE', $installmentActive);
            Configuration::updateValue('TPAY_BASIC_ACTIVE', $basicActive);
            Configuration::updateValue('TPAY_BLIK_ACTIVE', $blikActive);
            Configuration::updateValue('TPAY_BANNER', $bannerActive);
            /**
             * debug option.
             */
            $debug = (int)Tools::getValue('TPAY_DEBUG');
            Configuration::updateValue('TPAY_DEBUG', $debug);

            /**
             * debug option.
             */
            $checkIp = (int)Tools::getValue('TPAY_CHECK_IP');
            Configuration::updateValue('TPAY_CHECK_IP', $checkIp);

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


            /**
             * basic settings validation.
             */
            $userKey = (string)Tools::getValue('TPAY_KEY');
            $userId = Tools::getValue('TPAY_ID');
            $apiKey = (string)Tools::getValue('TPAY_APIKEY');
            $apiPass = Tools::getValue('TPAY_APIPASS');

            Configuration::updateValue('TPAY_KEY', $userKey);
            Configuration::updateValue('TPAY_ID', $userId);
            Configuration::updateValue('TPAY_APIKEY', $apiKey);
            Configuration::updateValue('TPAY_APIPASS', $apiPass);

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

        $generalSettings = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Basic settings'),
                    'image' => $this->_path . 'views/img/logo.jpg',
                ),
                'input'  => array(
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('User Id'),
                        'name'     => 'TPAY_ID',
                        'size'     => 50,
                        'required' => true,
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Security code'),
                        'name'     => 'TPAY_KEY',
                        'size'     => 50,
                        'required' => true,
                    ),
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('Debug mode'),
                        'name'    => 'TPAY_DEBUG',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_debug_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'tpay_debug_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                        'desc'    => '<b>' . $this->l('WARNING') . '</b>' . $this->l(' turn off in production mode'),
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Google analytics code'),
                        'name'     => 'TPAY_GOOGLE_ID',
                        'size'     => 50,
                        'required' => false,
                        'desc'     => $this->l('Complement this box will allow the module to send statistics'),
                    ),
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('Check the IP address for notification server'),
                        'name'    => 'TPAY_CHECK_IP',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_check_ip_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'tpay_check_ip_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('tpay payments banner on product cards'),
                        'name'    => 'TPAY_BANNER',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_banner_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'tpay_banner_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('Surcharge for the use of payment'),
                        'name'    => 'TPAY_SURCHARGE_ACTIVE',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_surcharge_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'tpay_surcharge_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'radio',
                        'label'   => $this->l('Surcharge type'),
                        'name'    => 'TPAY_SURCHARGE_TYPE',
                        'is_bool' => false,
                        'class'   => 'child',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_surcharge_type_on',
                                'value' => TPAY_SURCHARGE_AMOUNT,
                                'label' => $this->l('Quota'),
                            ),
                            array(
                                'id'    => 'tpay_surcharge_type_off',
                                'value' => TPAY_SURCHARGE_PERCENT,
                                'label' => $this->l('Percentage'),
                            ),
                        ),
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Surcharge value'),
                        'name'     => 'TPAY_SURCHARGE_VALUE',
                        'size'     => 50,
                        'required' => false,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ),
            ),
        );

        $basicPayment = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings for standard payment'),
                    'image' => $this->_path . 'views/img/logo.jpg',
                ),
                'input'  => array(
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('Payment active'),
                        'name'    => 'TPAY_BASIC_ACTIVE',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'tpay_active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('Show installments payment option (over 300zł)'),
                        'name'    => 'TPAY_INSTALLMENTS_ACTIVE',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_installments_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'tpay_installments_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'radio',
                        'label'   => $this->l('View for payment channels'),
                        'name'    => 'TPAY_BANK_ON_SHOP',
                        'is_bool' => false,
                        'class'   => 'child',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_bank_selection_redirect',
                                'value' => TPAY_VIEW_REDIRECT,
                                'label' => $this->l('Redirect to tpay'),
                            ),
                            array(
                                'id'    => 'tpay_bank_selection_icons',
                                'value' => TPAY_VIEW_ICONS,
                                'label' => $this->l('Tiles'),
                            ),
                            array(
                                'id'    => 'tpay_bank_selection_list',
                                'value' => TPAY_VIEW_LIST,
                                'label' => $this->l('List'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('Show tpay regulations on site'),
                        'name'    => 'TPAY_SHOW_REGULATIONS',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'tpay_regulations_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'tpay_regulations_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ),
            ),
        );
        $blikPayment = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings for blik level 0 payment'),
                    'image' => $this->_path . 'views/img/logo.jpg',
                ),
                'input'  => array(
                    array(
                        'type'    => $switch,
                        'label'   => $this->l('Payment active'),
                        'name'    => 'TPAY_BLIK_ACTIVE',
                        'is_bool' => true,
                        'class'   => 't',
                        'values'  => array(
                            array(
                                'id'    => 'blik_active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'blik_active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('API key'),
                        'name'     => 'TPAY_APIKEY',
                        'size'     => 50,
                        'required' => true,
                    ),
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('API password'),
                        'name'     => 'TPAY_APIPASS',
                        'size'     => 50,
                        'required' => true,
                    ),

                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ),
            ),
        );
        return array($generalSettings, $basicPayment, $blikPayment);
    }

    /**
     * Returns config fields array.
     *
     * @return array
     */
    private function getConfigFieldsValues()
    {
        return array(
            'TPAY_ID'                  => Configuration::get('TPAY_ID'),
            'TPAY_KEY'                 => Configuration::get('TPAY_KEY'),
            'TPAY_BANK_ON_SHOP'        => Configuration::get('TPAY_BANK_ON_SHOP'),
            'TPAY_BASIC_ACTIVE'        => Configuration::get('TPAY_BASIC_ACTIVE'),
            'TPAY_BLIK_ACTIVE'         => Configuration::get('TPAY_BLIK_ACTIVE'),
            'TPAY_APIKEY'              => Configuration::get('TPAY_APIKEY'),
            'TPAY_APIPASS'             => Configuration::get('TPAY_APIPASS'),
            'TPAY_DEBUG'               => Configuration::get('TPAY_DEBUG'),
            'TPAY_GOOGLE_ID'           => Configuration::get('TPAY_GOOGLE_ID'),
            'TPAY_CHECK_IP'            => Configuration::get('TPAY_CHECK_IP'),
            'TPAY_SHOW_REGULATIONS'    => Configuration::get('TPAY_SHOW_REGULATIONS'),
            'TPAY_SURCHARGE_ACTIVE'    => Configuration::get('TPAY_SURCHARGE_ACTIVE'),
            'TPAY_SURCHARGE_TYPE'      => Configuration::get('TPAY_SURCHARGE_TYPE'),
            'TPAY_SURCHARGE_VALUE'     => Configuration::get('TPAY_SURCHARGE_VALUE'),
            'TPAY_BANNER'              => Configuration::get('TPAY_BANNER'),
            'TPAY_INSTALLMENTS_ACTIVE' => Configuration::get('TPAY_INSTALLMENTS_ACTIVE'),
        );
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

        $payment_options = $this->hookPayment(true);

        return $payment_options;
    }

    /**
     * Render payment choice blocks.
     *
     * @param bool $returnPayments
     *
     * @return bool
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
        $installmentsActive = (int)Configuration::get('TPAY_INSTALLMENTS_ACTIVE');
        if ($basicActive === 1) {

            $paymentLink = $this->context->link->getModuleLink(
                'tpay',
                'validation',
                array('type' => TPAY_PAYMENT_BASIC)
            );


            $availablePayments[] = array(
                'type'        => TPAY_PAYMENT_BASIC,
                'paymentLink' => $paymentLink,
                'title'       => $this->l('Pay by tpay'),
                'cta_text'    => $this->l('Pay by tpay'),
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

        if ($currency->iso_code !== 'PLN') {
            $availablePayments = array();
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
