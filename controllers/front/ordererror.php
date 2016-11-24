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

/**
 * Class TpayOrderErrorModuleFrontController.
 */
class TpayOrderErrorModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->context->controller->addCss(_MODULE_DIR_.'tpay/views/css/style.css');
        $this->display_column_left = false;

        parent::initContent();

        $this->context->cookie->last_order = false;
        $this->context->cookie->__unset('last_order');

        $this->setTemplate('orderError.tpl');
    }
}
