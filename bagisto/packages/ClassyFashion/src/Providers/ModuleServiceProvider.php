<?php

namespace ClassyFashion\Providers;

use ClassyFashion\Models\Sales\Order;
use Webkul\Core\Providers\CoreModuleServiceProvider;
use Webkul\Sales\Contracts\Order as OrderContract;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Override the core order with the Classy Fashion order
     * (report 9.5/9.7 statuses). Registered last in config/concord.php
     * so this mapping wins over the Sales module one.
     */
    protected $models = [
        OrderContract::class => Order::class,
    ];
}
