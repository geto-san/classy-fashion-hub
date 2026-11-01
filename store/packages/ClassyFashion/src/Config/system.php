<?php

use ClassyFashion\Support\Storefront;

$storefrontFields = [];

foreach (Storefront::fields() as $name => $type) {
    $storefrontFields[] = [
        'name' => $name,
        'title' => 'classy-fashion::storefront.fields.'.$name,
        'type' => $type === 'boolean' ? 'boolean' : ($type === 'textarea' ? 'textarea' : 'text'),
        'channel_based' => true,
        'locale_based' => true,
    ];
}

return [
    [
        'key' => 'general.content.storefront',
        'name' => 'classy-fashion::storefront.editor_title',
        'info' => 'classy-fashion::storefront.editor_help',
        'sort' => 1,
        'fields' => $storefrontFields,
    ],
    [
        'key' => 'sales.payment_methods.mobilemoney',
        'name' => 'classy-fashion::app.configuration.mobile_money',
        'info' => 'classy-fashion::app.configuration.mobile_money_info',
        'sort' => 10,
        'fields' => [
            [
                'name' => 'active',
                'title' => 'admin::app.configuration.index.sales.payment-methods.status',
                'type' => 'boolean',
                'channel_based' => true,
                'locale_based' => false,
            ], [
                'name' => 'title',
                'title' => 'admin::app.configuration.index.sales.payment-methods.title',
                'type' => 'text',
                'depends' => 'active:1',
                'validation' => 'required_if:active,1',
                'channel_based' => true,
                'locale_based' => true,
            ], [
                'name' => 'description',
                'title' => 'admin::app.configuration.index.sales.payment-methods.description',
                'type' => 'textarea',
                'depends' => 'active:1',
                'channel_based' => true,
                'locale_based' => true,
            ],
        ],
    ],
];
