{include file="$orderSummaryPath"}
{$form}

<p class="cart_navigation clearfix" id="cart_navigation">
    <a class="button-exclusive btn btn-default" href="{$link->getPageLink('order', true)|escape:'htmlall':'UTF-8'}">
        <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='tpay'}
    </a>
</p>
