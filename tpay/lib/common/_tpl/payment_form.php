<form id="tpay-payment" class="tpay-form" action="<?php echo $data['action_url'] ?>" method="POST">
    <?php foreach ($data['fields'] as $name => $value) {
    ?>
        <input <?php if ($name === 'kanal') {
        echo ' id="tpay-channel-input" ';
    } elseif ($name === 'akceptuje_regulamin') {
        echo ' id="tpay-regulations-input" ';
    } ?> type="hidden"
                                                                                      name="<?php echo $name ?>"
                                                                                      value="<?php echo $value ?>">
    <?php

}
?>
    <input id="tpay-payment-submit" type="submit" value="<?php Tpay\Lang::l('pay') ?>">
</form>