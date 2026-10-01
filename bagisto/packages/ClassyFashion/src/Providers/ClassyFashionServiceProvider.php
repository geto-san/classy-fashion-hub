<?php

namespace ClassyFashion\Providers;

use ClassyFashion\Observers\ProductInventoryObserver;
use Illuminate\Support\ServiceProvider;
use Webkul\Product\Models\ProductInventory as WebkulProductInventory;

class ClassyFashionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        WebkulProductInventory::observe(ProductInventoryObserver::class);

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'classy-fashion');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'classy-fashion');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');
    }
}
