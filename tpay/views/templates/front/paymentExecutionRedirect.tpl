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
{literal}
    <script type="text/javascript">
        function submitTpayForm() {
            document.getElementById('tpay-payment').submit();
        }
    </script>
{/literal}
<body onload="submitTpayForm()">
<form id="tpay-payment" class="tpay-form" action="https://secure.tpay.com" method="POST">
    {foreach from=$paymentConfig key=name item=value}
        <input type="hidden" value="{$value|escape:'htmlall':'UTF-8'}" name="{$name|escape:'htmlall':'UTF-8'}">
    {/foreach}

    <input id="tpay-payment-submit" type="submit"
           value="{l s='Click here if browser does not redirect you automatically' mod='tpay'}">
</form>
</body>
