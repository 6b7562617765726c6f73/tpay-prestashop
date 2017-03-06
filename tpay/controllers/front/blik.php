<?php
/**
 * NOTICE OF LICENSE.
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
 */

require_once _PS_MODULE_DIR_.'tpay/controllers/front/blikClass.php';

class TpayBlikModuleFrontController extends ModuleFrontController
{
    const TPAY_URL = 'https://secure.transferuj.pl';

    public function postProcess()
    {
        $data = array(
            'id'=>Tools::getValue('id'),
            'kwota'=>Tools::getValue('kwota'),
            'crc'=>Tools::getValue('crc'),
            'opis'=>Tools::getValue('opis'),
            'md5sum'=>Tools::getValue('md5sum'),
            'nazwisko'=>Tools::getValue('nazwisko'),
            'imie'=>Tools::getValue('imie'),
            'email'=>Tools::getValue('email'),
            'akceptuje_regulamin'=>Tools::getValue('akceptuje_regulamin'),
            'kanal'=>Tools::getValue('kanal'),
            'pow_url'=>Tools::getValue('pow_url'),
            'pow_url_blad'=>Tools::getValue('pow_url_blad'),
            'api_password'=>Configuration::get('TPAY_APIPASS'),
        );

        $api_key = Configuration::get('TPAY_APIKEY');
        $url = static::TPAY_URL.'/api/gw/'.$api_key.'/transaction/create';

        $xml = (new SimpleXMLElement(TpayBlik::doCurlRequest($url, $data)));

        if ((string)$xml->result[0]=='1') {
            $postData2 = array();
            $postData2['code'] = Tools::getValue('blikCode');
            $postData2['title'] = (string)$xml->title[0];
            $postData2['api_password'] = $data['api_password'];

            $url = static::TPAY_URL.'/api/gw/' . $api_key . '/transaction/blik';
            $respBlik = (new SimpleXMLElement(TpayBlik::doCurlRequest($url, $postData2)));

            if ((string)$respBlik->result[0] == '1') {
                $pow_url = $data['pow_url'];
                Tools::redirect($pow_url);
                die();
            } else {
                    $pow_url_blad = $data['pow_url_blad'];
                    Tools::redirect($pow_url_blad);
                    die();
            }
        }
    }
}
