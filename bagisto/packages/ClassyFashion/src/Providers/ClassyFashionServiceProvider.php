<?php

namespace ClassyFashion\Providers;

use Illuminate\Support\ServiceProvider;

class ClassyFashionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'classy-fashion');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'classy-fashion');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');
    }
}
