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
    ],
];
