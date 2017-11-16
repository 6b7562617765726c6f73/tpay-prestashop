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
<h4>{l s='An error occurred while ordering. Contact the seller or choose another payment method.' mod='tpay'}</h4>

{if isset($google_id)}
    <script type="text/javascript">
        {literal}
        (function (i, s, o, g, r, a, m) {
            i['GoogleAnalyticsObject'] = r;
            i[r] = i[r] || function () {
                        (i[r].q = i[r].q || []).push(arguments)
                    }, i[r].l = 1 * new Date();
            a = s.createElement(o),
                    m = s.getElementsByTagName(o)[0];
            a.async = 1;
            a.src = g;
            m.parentNode.insertBefore(a, m)
        })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');
        {/literal}
        ga('create', '{$google_id|escape:'htmlall':'UTF-8'}', 'auto', {$linker});
        ga('require', 'linker');
        ga('linker:autoLink', ['secure.tpay.com']);
        ga('send', 'pageview');
    </script>
{/if}
