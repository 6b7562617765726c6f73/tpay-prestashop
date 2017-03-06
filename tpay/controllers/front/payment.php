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
 *  @author    tpay.com
 *  @copyright 2010-2016 tpay.com
 *  @license   LICENSE.txt
 */

require_once _PS_MODULE_DIR_.'/tpay/helpers/TpayHelperClient.php';

/**
 * Class TpayPaymentModuleFrontController.
 */
class TpayPaymentModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->context->controller->addCss(_MODULE_DIR_.'tpay/views/css/style.css');
        $this->display_column_left = false;

        parent::initContent();

        $cart = $this->context->cart;
        $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
        $paymentType = Tools::getValue('type');

        $this->context->smarty->assign(array(
            'orderTotal' => $orderTotal,
            'paymentLink' => $this->context->link->getModuleLink('tpay', 'validation', array('type' => $paymentType)),
        ));

        $surcharge = TpayHelperClient::getSurchargeValue($orderTotal);
        if (!empty($surcharge)) {
            $this->context->smarty->assign('surcharge', $surcharge);
        }

        switch ($paymentType) {

            case TPAY_PAYMENT_BANK_ON_SHOP:
                $this->setTemplate('paymentExecution.tpl');
                break;
            case TPAY_PAYMENT_BASIC:
            case TPAY_PAYMENT_INSTALLMENTS:
                $this->setTemplate('paymentExecutionRedirect.tpl');
                break;
            default:
                Tools::redirect($this->context->link->getPageLink('order', true, null, 'step=3'));
                break;
        }
    }
}
