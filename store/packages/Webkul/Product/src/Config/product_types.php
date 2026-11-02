<?php

use Webkul\Product\Type\Bundle;
use Webkul\Product\Type\Configurable;
use Webkul\Product\Type\Grouped;
use Webkul\Product\Type\Simple;

return [
    'simple' => [
        'key' => 'simple',
        'name' => 'product::app.type.simple',
        'class' => Simple::class,
        'sort' => 1,
    ],

    'configurable' => [
        'key' => 'configurable',
        'name' => 'product::app.type.configurable',
        'class' => Configurable::class,
        'sort' => 2,
    ],

    'grouped' => [
        'key' => 'grouped',
        'name' => 'product::app.type.grouped',
        'class' => Grouped::class,
        'sort' => 3,
    ],

    'bundle' => [
        'key' => 'bundle',
        'name' => 'product::app.type.bundle',
        'class' => Bundle::class,
        'sort' => 4,
    ],

    'virtual' => [
        'key' => 'virtual',
        'name' => 'product::app.type.virtual',
        'class' => 'Webkul\Product\Type\Virtual',
        'sort' => 5,
    ],
];
