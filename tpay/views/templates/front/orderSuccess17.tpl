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
{extends file="page.tpl"}
{block name='page_content'}
<div id="tpay-success">
    <img src="{$modules_dir|escape:'htmlall':'UTF-8'}tpay/views/img/tpay_logo.png" alt="{l s='tpay logo' mod='tpay'}"
         width="213" height="51"/><br/><br/>
    <h4>{l s='Thank you for your order and we invite you to further ' mod='tpay'} <a
            href="/">{l s='shopping' mod='tpay'}</a>.</h4>
    {if isset($redirectLink)}
    <h4>{l s='Browse list of your' mod='tpay'} <a href="{$redirectLink}">{l s='orders' mod='tpay'}</a>.</h4>
    {/if}
</div>
{if $display_OCS === true}
{$confirmation_script nofilter}
{/if}
{/block}
