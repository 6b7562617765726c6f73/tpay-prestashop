<link rel="stylesheet" type="text/css" href="<?php echo $data['static_files_url'] ?>card/_css/gate.css"/>
<form method="post" id="card_payment_form" name="card_payment_form">
    <input type="hidden" name="carddata" id="carddata" value=""/>

    <div id="powered_by"></div>
    <div id="card_payment">
        <label for="card_number"><?php Tpay\Lang::get('card_number') ?></label>
        <input id="card_number" pattern="\d*" autocompletetype="cc-number" size="30" type="tel" autocomplete="off"
               maxlength="23" placeholder="0000 0000 0000 0000" tabindex="1" value=""/>

        <div id="expiry_date_wrapper">
            <label for="transaction_card_expiry_month"><?php Tpay\Lang::l('expiration_date') ?></label><br>
            <input id="expiry_date" maxlength="9" type="text" placeholder="00 / 00" autocomplete="off"
                   autocompletetype="cc-exp" tabindex="2" value=""/>
        </div>
        <div id="cvc_wrapper">
            <label for="cvc">CSC</label><span class="info_button" title="<?php Tpay\Lang::l('signature') ?>">?</span>
            <input id="cvc" maxlength="3" type="tel" autocomplete="off" autocompletetype="cc-cvc" placeholder="000"
                   tabindex="4" value=""/>
        </div>
        <br>
        <label for="c_name"><?php Tpay\Lang::l('name_on_card') ?></label>
        <input type="text" id="c_name" placeholder="<?php Tpay\Lang::l('name_surname') ?>" autocomplete="off" name="client_name"
               maxlength="64" tabindex="5" value=""/>

        <label for="c_email">E-mail</label>
        <input type="text" id="c_email" data-formance_algorithm="complex" autocomplete="off" placeholder="E-mail"
               name="client_email" value="" maxlength="64" tabindex="6">

        <div class="amPmCheckbox">
            <input type="checkbox" id="card_save" name="card_save"/>&nbsp;&nbsp;&nbsp;<label for="card_save"><?php Tpay\Lang::l('save_card') ?></label> <span class="info_button"
                                   title="<?php Tpay\Lang::l('save_card_info') ?>">?</span>
        </div>
    </div>

    <div id="card_icons">
        <div class="card_icon" id="visa"></div>
        <div class="card_icon" id="master"></div>
        <div class="card_icon" id="maestro"></div>
        <div class="card_icon" id="diners"></div>
        <div class="card_icon" id="jcb"></div>
    </div>

    <p id="info_msg"></p>

    <div id="loading_scr" style="display:none"><img src="<?php echo $data['static_files_url'] ?>common/_img/loading.gif" style="vertical-align: middle;"/>&nbsp;&nbsp;<?php Tpay\Lang::l('processing') ?>
    </div>
</form>
<button id="continue_btn">&nbsp;<?php Tpay\Lang::l('card_payment') ?>&nbsp;</button>
<script type="text/javascript" src="<?php echo $data['static_files_url'] ?>common/_js/jquery.min.js"></script>
<script type="text/javascript" src="<?php echo $data['static_files_url'] ?>common/_js/jquery.formance.min.js"></script>
<script type="text/javascript" src="<?php echo $data['static_files_url'] ?>common/_js/cardpayment.js"></script>
<script type="text/javascript" src="<?php echo $data['static_files_url'] ?>common/_js/jsencrypt.min.js"></script>
<script type="text/javascript" src="<?php echo $data['static_files_url'] ?>common/_js/string_routines.js"></script>

<script type="text/javascript">
    $(document).ready(function () {
        new CardPayment('<?php echo $data['payment_redirect_path'] ?>', "<?php echo $data['rsa_key'] ?>");
    });
</script>