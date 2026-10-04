<?php

use Webkul\Sales\Models\Order;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;

/**
 * Admin/worker route matrix (report 9.1): every area reachable by the
 * right role, admin-only areas refused to workers with 401 (server-side,
 * not just hidden menus), and UGX formatting consistent for both.
 */
function matrixAdmin(): Admin
{
    return Admin::where('email', 'admin@example.com')->firstOrFail();
}

function matrixWorker(): Admin
{
    return Admin::where('email', 'worker@classy.local')->firstOrFail();
}

function matrixCheck(string $role, string $route, array $params, int $expected): void
{
    $admin = $role === 'admin' ? matrixAdmin() : matrixWorker();

    test()->actingAs($admin, 'admin');

    get(route($route, $params))->assertStatus($expected);
}

it('opens worker areas for the worker', function () {
    $order = Order::firstOrFail();

    $customerId = Webkul\Customer\Models\Customer::firstOrFail()->id;

    $pages = [
        'admin.dashboard.index'            => [],
        'admin.catalog.products.index'     => [],
        'admin.catalog.categories.index'   => [],
        'admin.sales.orders.index'         => [],
        'admin.sales.orders.view'          => [$order->id],
        'admin.sales.invoices.index'       => [],
        'admin.sales.shipments.index'      => [],
        'admin.customers.customers.index'  => [],
        'admin.customers.customers.view'   => [$customerId],
        'admin.reporting.sales.index'      => [],
        'admin.classy.payments.index'      => [],
    ];

    foreach ($pages as $route => $params) {
        matrixCheck('worker', $route, $params, 200);
    }

    // The owner reaches everything the worker does.
    foreach ($pages as $route => $params) {
        matrixCheck('admin', $route, $params, 200);
    }

    expect(true)->toBeTrue();
});

it('refuses admin-only areas to the worker', function () {
    $denied = [
        'admin.settings.users.index'       => [],
        'admin.settings.roles.index'       => [],
        'admin.configuration.index'        => [],
        'admin.reporting.products.index'   => [],
        'admin.reporting.customers.index'  => [],
        'admin.classy.reports.audit'       => [],
        'admin.classy.reports.profit'      => [],
        'admin.marketing.promotions.cart_rules.index' => [],
    ];

    foreach ($denied as $route => $params) {
        matrixCheck('worker', $route, $params, 401);
    }

    expect(true)->toBeTrue();
});

it('shows UGX consistently on staff pages', function () {
    $order = Order::firstOrFail();

    foreach (['admin', 'worker'] as $role) {
        $admin = $role === 'admin' ? matrixAdmin() : matrixWorker();

        $this->actingAs($admin, 'admin');

        get(route('admin.sales.orders.view', $order->id))
            ->assertOk()
            ->assertSee('UGX', false);
    }
});
