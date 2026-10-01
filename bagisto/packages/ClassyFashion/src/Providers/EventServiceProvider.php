<?php

namespace ClassyFashion\Providers;

use ClassyFashion\Listeners\OrderStatusButtons;
use ClassyFashion\Listeners\OrderStatusMapper;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        'sales.order.update-status.after' => [
            [OrderStatusMapper::class, 'mapCompletedToDispatched'],
        ],

        'bagisto.admin.sales.order.status_label.after' => [
            [OrderStatusButtons::class, 'addButtons'],
        ],
    ];
}
