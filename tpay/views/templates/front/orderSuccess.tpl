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
<div id="tpay-success">
    <img src="{$modules_dir|escape:'htmlall':'UTF-8'}tpay/views/img/tpay_logo.png" alt="{l s='tpay logo' mod='tpay'}" width="213" height="51"/>
    <h4>{l s='Thank you for your order and we invite you to further ' mod='tpay'} <a href="/">{l s='shopping' mod='tpay'}</a></h4>
</div>

{if isset($google_id)}
    <script type="text/javascript" >
        {literal}

        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
        {/literal}
        ga('create', '{$google_id|escape:'htmlall':'UTF-8'}', 'auto', {$linker});
        ga('require', 'linker');
        ga('linker:autoLink', ['secure.tpay.com'] );

        ga('send', 'pageview');
        ga('require', 'ecommerce', 'ecommerce.js');

        ga('ecommerce:addTransaction', {
            'id': '{$id_order|escape:'htmlall':'UTF-8'}', // Transaction ID. Required
            'affiliation': '{$shop|escape:'htmlall':'UTF-8'}', // Affiliation or store name
            'revenue': '{$total_to_pay|escape:'htmlall':'UTF-8'}', // Grand Total
            'shipping': '{$shipping|escape:'htmlall':'UTF-8'}', // Shipping
            'tax': '{$tax|escape:'htmlall':'UTF-8'}'                     // Tax
        });
        // add item might be called for every item in the shopping cart
        // where your ecommerce engine loops through each item in the cart and
        // prints out _addItem for each
        {foreach from=$tpay_products item=product name=products}

        ga('ecommerce:addItem', {
            'id': '{$id_order|escape:'htmlall':'UTF-8'}', // Transaction ID. Required
            'name': '{$product.product_name|escape:'htmlall':'UTF-8'}', // Product name. Required
            'sku': '{$product.product_reference|escape:'htmlall':'UTF-8'}', // SKU/code
            'category': '', // Category or variation
            'price': '{$product.product_price_wt|escape:'htmlall':'UTF-8'}', // Unit price
            'quantity': '{$product.product_quantity|escape:'htmlall':'UTF-8'}'                   // Quantity
        });
        {/foreach}

        ga('ecommerce:send');

    </script>
{/if}
