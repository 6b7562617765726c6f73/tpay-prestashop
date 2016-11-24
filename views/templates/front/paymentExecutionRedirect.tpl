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
{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='tpay'}">{l s='Checkout' mod='tpay'}</a>
    <span class="navigation-pipe">{$navigationPipe|escape:'htmlall':'UTF-8'}</span>{l s='Check payment' mod='tpay'}
{/capture}

<h2>{l s='Order summary' mod='tpay'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if isset($nbProducts) && $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.' mod='tpay'}</p>
{else}
    <div id="tpay-order-summary">
        <div id="tpay-summary-content">
            <h4>{l s='tpay payment' mod='tpay'}</h4>
            <div>{l s='Short order details' mod='tpay'}</div>
            <ul>
                <li>{l s='Order price ' mod='tpay'}{displayPrice price=$orderTotal}
                    {if $use_taxes == 1}
                        {l s='(total)' mod='tpay'}
                    {/if}
                </li>
                {if isset($surcharge)}
                    <li>
                        {l s='payment surcharge' mod='tpay'}{displayPrice price=$surcharge}
                    </li>
                {/if}
                <li>{l s='By clicking' mod='tpay'} <b>{l s='"Confirm order"' mod='tpay'}</b> {l s='button You will be redirect to payment' mod='tpay'}</li>
            </ul>
        </div>

        <div id="tpay-nav">
            <a id="tpay-back" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'htmlall':'UTF-8'}">
                {l s='Another payment methods' mod='tpay'}
                <i class="fa fa-chevron-left">&#xf053</i>
            </a>
            <a id="tpay-submit" href="{$paymentLink|escape:'htmlall':'UTF-8'}">
                {l s='Confirm order' mod='tpay'}
            </a>
        </div>

    </div>
{/if}
