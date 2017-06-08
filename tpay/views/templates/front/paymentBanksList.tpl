{*
* NOTICE OF LICENSE
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
*}
    <style>
        #bank-selection-form {
            overflow: hidden
        }

        .bank-block.bank-active {
            box-shadow: 0 0 10px 3px #15428F
        }

        .bank-block label input {
            display: none
        }

        .bank-block {
            width: 150px;
            height: 70px;
            float: left;
            position: relative;
            border: 1px solid #B2B2B2;
            margin: 6px;
            background-size: contain;
            background-position: center center;
            background-repeat: no-repeat;
            background-color: #FFF;
        }

        .bank-block label {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .checker {
            display: inline-block;
        }
    </style>
{include file="$orderSummaryPath"}
{include file="$blikPath" paymentConfig=$paymentConfig}
<br/>
<div class="insidebg" id="insidebg">
    <div>
        {l s='Please choose the bank to make a payment.' mod='tpay'}
    </div>
    <br>
    <select name="bank_list" id="tpay-bank-list" onchange="changeBank()"></select>
    {if $showRegulations}
        <br/><br/>
        <input id="tpay-accept-regulations-checkbox" type="checkbox" value="0">
        <label for="tpay-accept-regulations-checkbox">
            {l s='accept' mod='tpay'} <a href="{$paymentConfig.regulation_url|escape:'htmlall':'UTF-8'}" target="_blank">{l s='regulations' mod='tpay'}</a>
        </label>
    {/if}
    {include file="$paymentBasicPath" paymentConfig=$paymentConfig}
    <br>


{literal}
    <script>
        var s = document.createElement('script'),
                submit_form_input = document.getElementById('tpay-payment-submit'),
                regulations_form_input = document.getElementById('tpay-regulations-input'),
                regulation_checkbox = document.getElementById('tpay-accept-regulations-checkbox'),
                changeBank = function () {
                    document.getElementById('tpay-channel-input').value = document.getElementById('tpay-bank-list').value;
                };

        s.src = 'https://secure.tpay.com/channels-{/literal}{$paymentConfig.merchant_id|escape:'htmlall':'UTF-8'}{literal}1.js';
        s.onload = function () {
            var str = '', i;
            for (i in tr_channels) {
                var channel = tr_channels[i],
                        id = channel[0],
                        name = channel[1];
                str += '<option value="' + id + '" >'+name+'</option>';
            }
            document.getElementById('tpay-bank-list').innerHTML = str;
            changeBank();
        };
        document.getElementsByTagName('head')[0].appendChild(s);

        {/literal}{if $showRegulations}{literal}
        submit_form_input.onclick = function () {
            if (regulations_form_input.value == 0) {
                alert('{/literal}{l s = 'accept regulations'}{literal}');
                return false;
            }
            return true;
        };

        regulation_checkbox.onchange = function () {
            regulations_form_input.value = (this.checked) ? 1 : 0;
        };
        {/literal}{/if}{literal}
    </script>
{/literal}
    <script>
        $(document).ready(function () {
            $('#tpay-form').find('input[type=submit]').val("{l s='Pay' mod='tpay'}").addClass('tpay');
        })
    </script>
</div>
