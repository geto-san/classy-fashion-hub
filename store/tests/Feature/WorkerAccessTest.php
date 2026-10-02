<?php

use Database\Seeders\ClassyFashionSeeder;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(ClassyFashionSeeder::class);

    $this->worker = Admin::where('email', 'worker@classy.local')->firstOrFail();
});

it('allows the worker to reach the dashboard, products and orders', function () {
    $this->actingAs($this->worker, 'admin');

    get(route('admin.dashboard.index'))->assertOk();
    get(route('admin.catalog.products.index'))->assertOk();
    get(route('admin.sales.orders.index'))->assertOk();
});

it('refuses the worker on admin-only pages', function () {
    $this->actingAs($this->worker, 'admin');

    get(route('admin.settings.roles.index'))->assertUnauthorized();
    get(route('admin.settings.users.index'))->assertUnauthorized();
    get(route('admin.configuration.index'))->assertUnauthorized();
});

it('still allows the administrator everywhere the worker is refused', function () {
    $admin = Admin::where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin, 'admin');

    get(route('admin.settings.roles.index'))->assertOk();
    get(route('admin.settings.users.index'))->assertOk();
});
