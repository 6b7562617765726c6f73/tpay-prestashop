<?php
/**
 * Created by tpay.com.
 * Date: 26.05.2017
 * Time: 17:30
 */
return array(
    'form' => array(
        'legend' => array(
            'title' => $this->l('Settings for blik level 0 payment'),
            'image' => $this->_path . 'views/img/logo.jpg',
        ),
        'input'  => array(
            array(
                'type'    => $switch,
                'label'   => $this->l('Payment active'),
                'name'    => 'TPAY_BLIK_ACTIVE',
                'is_bool' => true,
                'class'   => 't',
                'values'  => array(
                    array(
                        'id'    => 'blik_active_on',
                        'value' => 1,
                        'label' => $this->l('Yes'),
                    ),
                    array(
                        'id'    => 'blik_active_off',
                        'value' => 0,
                        'label' => $this->l('No'),
                    ),
                ),
            ),
            array(
                'type'     => 'text',
                'label'    => $this->l('API key'),
                'name'     => 'TPAY_APIKEY',
                'size'     => 50,
                'required' => true,
            ),
            array(
                'type'     => 'text',
                'label'    => $this->l('API password'),
                'name'     => 'TPAY_APIPASS',
                'size'     => 50,
                'required' => true,
            ),

        ),
        'submit' => array(
            'title' => $this->l('Save'),
            'class' => 'button',
        ),
    ),
);
