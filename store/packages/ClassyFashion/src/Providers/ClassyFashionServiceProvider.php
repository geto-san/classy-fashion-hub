<?php

namespace ClassyFashion\Providers;

use ClassyFashion\Console\Commands\AuditMedia;
use ClassyFashion\Console\Commands\CheckStorage;
use ClassyFashion\Console\Commands\CopyMedia;
use ClassyFashion\Console\Commands\DescribeProducts;
use ClassyFashion\Console\Commands\ExpirePayments;
use ClassyFashion\Console\Commands\ImportProductPhotos;
use ClassyFashion\Console\Commands\RepairImages;
use ClassyFashion\Console\Commands\SecureAccounts;
use ClassyFashion\Console\Commands\SetupUgandaHomepage;
use ClassyFashion\Console\Commands\TransferDatabase;
use ClassyFashion\DataGrids\OrderDataGrid;
use ClassyFashion\Http\Middleware\ApplyClassyBrand;
use ClassyFashion\Observers\ProductInventoryObserver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Webkul\Product\Models\ProductInventory as WebkulProductInventory;

class ClassyFashionServiceProvider extends ServiceProvider
{
    /**
     * Register the shop extensions and configuration.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);

        $this->app->register(CloudStorageServiceProvider::class);

        $this->app->bind(\Webkul\Admin\DataGrids\Sales\OrderDataGrid::class, OrderDataGrid::class);

        $this->mergeConfigFrom(__DIR__.'/../Config/classy.php', 'classy');

        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');

        $this->mergeConfigFrom(__DIR__.'/../Config/admin-menu.php', 'menu.admin');

        $this->mergeConfigFrom(__DIR__.'/../Config/payment-methods.php', 'payment_methods');

        $this->mergeConfigFrom(__DIR__.'/../Config/system.php', 'core');
    }

    /**
     * Register shop views, routes, migrations, observers, and commands.
     */
    public function boot(): void
    {
        config(['app.default_country' => config('app.default_country') ?: 'UG']);

        $this->app->make(Router::class)->pushMiddlewareToGroup('web', ApplyClassyBrand::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        WebkulProductInventory::observe(ProductInventoryObserver::class);

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'classy-fashion');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'classy-fashion');

        $this->app->booted(function () {
            $this->app['view']->prependNamespace('shop', __DIR__.'/../Resources/views/overrides/shop');

            if (config('classy.client_admin', true)) {
                $this->app['view']->prependNamespace('admin', __DIR__.'/../Resources/views/overrides/admin');

                config(['debugbar.enabled' => false]);
                config(['menu.admin' => array_values(array_filter(
                    config('menu.admin', []),
                    fn ($item) => ! str_starts_with($item['key'], 'appearance')
                ))]);

                config(['core' => array_values(array_filter(
                    config('core', []),
                    fn ($item) => ! str_starts_with($item['key'], 'magic_ai')
                        && ! in_array($item['key'], [
                            'general.content.custom_scripts',
                            'general.content.speculation_rules',
                            'general.content.header_offer',
                        ])
                ))]);
            }
        });

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/shop-routes.php');

        $this->callAfterResolving(Schedule::class, function ($schedule) {
            $schedule->command('classy:expire-payments')->everyTenMinutes();
        });

        $this->commands([
            AuditMedia::class,
            CopyMedia::class,
            CheckStorage::class,
            TransferDatabase::class,
            RepairImages::class,
            ImportProductPhotos::class,
            SecureAccounts::class,
            ExpirePayments::class,
            DescribeProducts::class,
            SetupUgandaHomepage::class,
        ]);
    }
}
