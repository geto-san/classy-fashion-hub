<?php

return [
    [
        'key'   => 'sales.payment_methods.mobilemoney',
        'name'  => 'classy-fashion::app.configuration.mobile_money',
        'info'  => 'classy-fashion::app.configuration.mobile_money_info',
        'sort'  => 10,
        'fields' => [
            [
                'name'          => 'active',
                'title'         => 'admin::app.configuration.index.sales.payment-methods.status',
                'type'          => 'boolean',
                'channel_based' => true,
                'locale_based'  => false,
            ], [
                'name'          => 'title',
                'title'         => 'admin::app.configuration.index.sales.payment-methods.title',
                'type'          => 'text',
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1',
                'channel_based' => true,
                'locale_based'  => true,
            ], [
                'name'          => 'description',
                'title'         => 'admin::app.configuration.index.sales.payment-methods.description',
                'type'          => 'textarea',
                'depends'       => 'active:1',
                'channel_based' => true,
                'locale_based'  => true,
            ], [
                'name'          => 'sandbox',
                'title'         => 'classy-fashion::app.configuration.sandbox',
                'type'          => 'boolean',
                'depends'       => 'active:1',
                'channel_based' => false,
                'locale_based'  => false,
            ],
        ],
    ],
];
