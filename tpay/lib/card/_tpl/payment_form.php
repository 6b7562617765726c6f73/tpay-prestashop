<form id="tpay-payment" class="tpay-form" action="<?php echo $data['action_url'] ?>" method="POST">
    <input type="hidden" name="sale_auth" value="<?php echo $data['sale_auth'] ?>"/>
    <input type="hidden" name="id" value="<?php echo $data['merchant_id'] ?>"/>
    <input id="tpay-payment-submit" type="submit" value="<?php Tpay\Lang::l('pay') ?>">
</form>