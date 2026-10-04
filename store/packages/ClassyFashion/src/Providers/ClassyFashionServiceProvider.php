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

        // Admin order list that renders/filters the report statuses.
        $this->app->bind(\Webkul\Admin\DataGrids\Sales\OrderDataGrid::class, \ClassyFashion\DataGrids\OrderDataGrid::class);

        $this->mergeConfigFrom(__DIR__.'/../Config/classy.php', 'classy');

        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');

        $this->mergeConfigFrom(__DIR__.'/../Config/admin-menu.php', 'menu.admin');

        $this->mergeConfigFrom(__DIR__.'/../Config/payment-methods.php', 'payment_methods');

        $this->mergeConfigFrom(__DIR__.'/../Config/system.php', 'core');
    }

    public function boot(): void
    {
        /*
         * Uganda store defaults (universal): country pre-selected across
         * address forms; timezone handled per channel (Africa/Kampala).
         */
        config(['app.default_country' => config('app.default_country') ?: 'UG']);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        WebkulProductInventory::observe(ProductInventoryObserver::class);

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'classy-fashion');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'classy-fashion');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/shop-routes.php');

        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
            $schedule->command('classy:expire-payments')->everyTenMinutes();
        });

        $this->commands([
            \ClassyFashion\Console\Commands\RepairImages::class,
            \ClassyFashion\Console\Commands\ImportProductPhotos::class,
            \ClassyFashion\Console\Commands\SecureAccounts::class,
            \ClassyFashion\Console\Commands\ExpirePayments::class,
            \ClassyFashion\Console\Commands\DescribeProducts::class,
        ]);
    }

}
