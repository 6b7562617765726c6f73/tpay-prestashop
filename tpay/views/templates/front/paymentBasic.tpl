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
{if $autoSubmit}
    <div>
        {l s='Thank you for your order, in a moment you will be redirected to the page tpay' mod='tpay'}
    </div>
    <br>
    <div>
        {l s='If you are redirected to the payment tpay.com does not happen automatically, press the button below.' mod='tpay'}
    </div>
    <br>
{/if}
{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}"
       title="{l s='Other payment methods' mod='tpay'}">
        {l s='Checkout' mod='tpay'}</a>
    <span class="navigation-pipe">{$navigationPipe}</span>{l s='Check payment' mod='tpay'}
{/capture}

<div id="tpay-form">
    <form id="tpay-payment" class="tpay-form"
          action="{$link->getModuleLink('tpay', 'payment', [], true)|escape:'htmlall':'UTF-8'}" method="POST">
        <input id="tpay-channel-input" type="hidden" name="channel" {if $installments}value="49" {/if}>
        <input id="tpay-payment-submit" type="submit" value="{l s='Pay' mod='tpay'}">
        <input id="tpay-regulations-input" type="hidden" value="0" name="regulations">
    </form>
    <p class="cart_navigation clearfix" id="cart_navigation">
        <a class="button-exclusive btn btn-default" href="{$link->getPageLink('order', true)|escape:'htmlall':'UTF-8'}">
            <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='tpay'}
        </a>
    </p>
</div>
<script>
    {literal}
    (function () {
        "use strict";
        var c = [], f = {}, a, e, d, b;
        if (!window.jQuery) {
            a = function (g) {
                c.push(g)
            };
            f.ready = function (g) {
                a(g)
            };
            e = window.jQuery = window.$ = function (g) {
                if (typeof g == "function") {
                    a(g)
                }
                return f
            };
            window.checkJQ = function () {
                if (!d()) {
                    b = setTimeout(checkJQ, 100)
                }
            };
            b = setTimeout(checkJQ, 100);
            d = function () {
                if (window.jQuery !== e) {
                    clearTimeout(b);
                    var g = c.shift();
                    while (g) {
                        jQuery(g);
                        g = c.shift()
                    }
                    b = f = a = e = d = window.checkJQ = null;
                    return true
                }
                return false
            }
        }
    })();

    {/literal}
    {if $autoSubmit}{literal}
    jQuery(document).ready(function () {
        setTimeout(function () {
            jQuery('#tpay-form').find('input[type=submit]').click();
        }, 3000);
        jQuery('#tpay-form').find('input[type=submit]').val("{/literal}{l s='Pay' mod='tpay'}{literal}");
    });

    {/literal}{/if}
</script>
