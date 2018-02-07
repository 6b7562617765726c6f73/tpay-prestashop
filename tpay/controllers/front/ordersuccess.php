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
require_once _PS_MODULE_DIR_ . 'tpay/controllers/front/confirmation.php';

/**
 * Class TpayOrderSuccessModuleFrontController.
 */
class TpayOrderSuccessModuleFrontController extends ModuleFrontController
{
    private $order;

    public function initContent()
    {
        $this->context->controller->addCss(_MODULE_DIR_ . 'tpay/views/css/style.css');
        $this->display_column_left = false;
        $this->context->cart->id = null;
        $this->context->smarty->assign(['redirectLink' => 'index.php?controller=history']);
        $callConfirmScript = false;
        if ($this->context->cookie->last_order !== false) {
            $callConfirmScript = true;
            $this->order = new Order((int)$this->context->cookie->last_order);
            $this->context->smarty->assign([
                'confirmation_script' => $this->displayOrderConfirmation(),
            ]);
        }
        $this->context->smarty->assign([
                'modules_dir' => _MODULE_DIR_,
                'display_OCS' => $callConfirmScript,
            ]);
        $this->context->cookie->last_order = false;
        $this->context->cookie->__unset('last_order');
        TPAY_PS_17 ? $this->setTemplate(TPAY_17_PATH . '/orderSuccess17.tpl') : $this->setTemplate('orderSuccess.tpl');
        parent::initContent();
    }

    /**
     * Execute the hook displayOrderConfirmation google script
     */
    private function displayOrderConfirmation()
    {
        $params = $this->displayHook();
        if ($params && is_array($params)) {
            return Hook::exec('displayOrderConfirmation', $params);
        }
        return false;

    }

    private function displayHook()
    {
        if (Validate::isLoadedObject($this->order)) {
            $currency = new Currency((int)$this->order->id_currency);
            $params = array();
            $params['order'] = $this->order;
            $params['objOrder'] = $this->order;
            $params['currencyObj'] = $currency;
            $params['currency'] = $currency->sign;
            $params['total_to_pay'] = $this->order->getOrdersTotalPaid();
            return $params;

        }
        return false;

    }

}
