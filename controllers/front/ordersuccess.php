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
 * include tpay client and model functions.
 */
require_once _PS_MODULE_DIR_.'tpay/controllers/front/confirmation.php';
/**
 * Class TpayOrderSuccessModuleFrontController.
 */
class TpayOrderSuccessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->context->controller->addCss(_MODULE_DIR_.'tpay/views/css/style.css');
        $this->display_column_left = false;

        parent::initContent();

        $googleId = Configuration::get('TPAY_GOOGLE_ID');
        if (!empty($googleId) && $this->context->cookie->last_order !== false) {
            $order_id = (int) $this->context->cookie->last_order;
            $cart = Cart::getCartByOrderId($order_id);
            $order = new Order($order_id);
            $products = $order->getProducts();

            $smarty_data = array(
                'google_id' => $googleId,
                'total_to_pay' => $cart->getOrderTotal(),
                'tax' => ($cart->getOrderTotal() - $order->total_paid_tax_excl),
                'products' => $products,
                'shop' => Configuration::get('PS_SHOP_NAME'),
                'shipping' => $order->total_shipping,
                'id_order' => $order_id,
                'order' => $order,
            );

            $this->context->smarty->assign($smarty_data);
        }
        $this->context->smarty->assign(array('modules_dir' => _MODULE_DIR_));
        $this->setTemplate('orderSuccess.tpl');
        $this->context->cookie->last_order = false;
        $this->context->cookie->__unset('last_order');
    }
}
