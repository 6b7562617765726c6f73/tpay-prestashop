<?php
use tpayLibs\src\_class_tpay\Utilities\Util;

/**
 * Created by tpay.com.
 * Date: 01.02.2018
 * Time: 15:56
 */
class TpayOrderStatusHandler extends Helper
{
    /**
     * Update orders statuses.
     *
     * @param int $orderId
     * @param string $tpayPaymentId
     * @param bool $error change to error status flag
     */
    public function setOrdersAsConfirmed($orderId, $tpayPaymentId, $error = false)
    {
        $order = new Order($orderId);
        $reference = $order->reference;
        $referencedOrders = Order::getByReference($reference)->getResults();
        foreach ($referencedOrders as $key => $orderObject) {
            if (!is_null($orderObject->id)) {
                $this->setOrderAsConfirmed((int)$orderObject->id, $tpayPaymentId, $error);
            }
        }
    }

    /**
     * Update order status.
     *
     * @param int $orderId
     * @param string $tpayPaymentId
     * @param bool $error change to error status flag
     */
    private function setOrderAsConfirmed($orderId, $tpayPaymentId, $error = false)
    {
        $orderHistory = new OrderHistory();
        $order = new Order($orderId);
        $ownStatusSetting = (int)Configuration::get('TPAY_OWN_STATUS') === 1;
        if ($ownStatusSetting) {
            $targetOrderState = !$error ? Configuration::get('TPAY_OWN_PAID') : Configuration::get('TPAY_OWN_ERROR');
        } else {
            $targetOrderState = !$error ? Configuration::get('TPAY_CONFIRMED') : Configuration::get('TPAY_ERROR');
        }
        $unpaidStatus = $ownStatusSetting ?
            (int)Configuration::get('TPAY_OWN_WAITING') : (int)Configuration::get('TPAY_NEW');
        if ((int)$order->current_state === $unpaidStatus) {
            $orderHistory->id_order = $orderId;
            $orderHistory->changeIdOrderState($targetOrderState, $orderId);
            $orderHistory->addWithemail(true);
            if (!$error) {
                $payments = $order->getOrderPaymentCollection();
                $payments[0]->transaction_id = $tpayPaymentId;
                $payments[0]->update();
            }
        }
    }

}
