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

        $this->mergeConfigFrom(__DIR__.'/../Config/payment-methods.php', 'payment_methods');

        $this->mergeConfigFrom(__DIR__.'/../Config/system.php', 'core');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        WebkulProductInventory::observe(ProductInventoryObserver::class);

        $this->registerCloudinaryDisk();

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'classy-fashion');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'classy-fashion');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadRoutesFrom(__DIR__.'/../Routes/shop-routes.php');

        $this->commands([
            \ClassyFashion\Console\Commands\PushMediaToCloud::class,
        ]);
    }

    /**
     * Register the Cloudinary disk when credentials exist (report media
     * persistence). The disk is only USED when FILESYSTEM_DISK=cloudinary,
     * so local development is unaffected.
     */
    protected function registerCloudinaryDisk(): void
    {
        if (! filled(env('CLOUDINARY_CLOUD_NAME'))) {
            return;
        }

        config([
            'filesystems.disks.cloudinary' => [
                'driver'     => 'cloudinary',
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
                'url'        => ['secure' => true],
            ],
        ]);
    }
}
