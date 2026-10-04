<?php

return [
    [
        'key'   => 'sales.orders.status',
        'name'  => 'classy-fashion::acl.orders.status',
        'route' => [
            'admin.classy.orders.status.update',
            'admin.classy.orders.partner.update',
        ],
        'sort'  => 5,
    ], [
        'key'   => 'reporting.profit',
        'name'  => 'classy-fashion::acl.reports.profit',
        'route' => 'admin.classy.reports.profit',
        'sort'  => 4,
    ], [
        'key'   => 'reporting.audit',
        'name'  => 'classy-fashion::acl.reports.audit',
        'route' => 'admin.classy.reports.audit',
        'sort'  => 5,
    ], [
        'key'   => 'sales.payments',
        'name'  => 'classy-fashion::acl.payments.view',
        'route' => 'admin.classy.payments.index',
        'sort'  => 6,
    ],
];
