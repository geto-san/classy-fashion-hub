<?php

use ClassyFashion\Listeners\OrderTimeline;
use ClassyFashion\Support\Audit;
use ClassyFashion\Support\Notify;
use Illuminate\Support\Facades\Mail;
use Webkul\Sales\Models\Order;
use Webkul\Theme\ViewRenderEventManager;

it('e-mails the customer when an order is delivered', function () {
    $order = Order::factory()->create(['status' => 'delivered', 'customer_email' => 'buyer@example.com']);

    Mail::shouldReceive('raw')
        ->once()
        ->withArgs(fn ($body) => str_contains($body, 'delivered') && str_contains($body, (string) $order->increment_id));

    Notify::orderStatus($order, 'delivered');
});

it('stays quiet for statuses the customer does not need to hear about', function () {
    $order = Order::factory()->create(['status' => 'pending', 'customer_email' => 'buyer@example.com']);

    Mail::shouldReceive('raw')->never();

    Notify::orderStatus($order, 'pending');
});

it('never breaks a status change when mail cannot be sent', function () {
    $order = Order::factory()->create(['status' => 'paid', 'customer_email' => 'buyer@example.com']);

    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('SMTP is down'));

    Notify::orderStatus($order, 'paid');

    expect(true)->toBeTrue(); // reached without an exception
});

it('e-mails the owner when stock runs low', function () {
    config(['mail.admin.address' => 'owner@example.com']);

    Mail::shouldReceive('raw')->once()->withArgs(fn ($body) => str_contains($body, 'SKU-LOW') && str_contains($body, '3'));

    Notify::lowStock('SKU-LOW', 3, 5);
});

it('does nothing about low stock when no owner address is set', function () {
    config(['mail.admin.address' => '']);

    Mail::shouldReceive('raw')->never();

    Notify::lowStock('SKU-LOW', 3, 5);
});

it('shows the customer the statuses an order has been through', function () {
    $order = Order::factory()->create(['status' => 'dispatched']);

    Audit::log($order, 'to paid', ['from' => 'confirmed', 'to' => 'paid'], null, 'order.status');
    Audit::log($order, 'to dispatched', ['from' => 'processing', 'to' => 'dispatched'], null, 'order.status');

    $manager = (new ViewRenderEventManager)->handleRenderEvent('bagisto.shop.customers.account.orders.view.before', ['order' => $order]);

    app(OrderTimeline::class)->show($manager);

    $html = implode('', $manager->getTemplates());

    expect($html)->toContain('Order placed')
        ->and($html)->toContain('Paid')
        ->and($html)->toContain('Dispatched');
});

it('shows no timeline for an order that has not progressed', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    $manager = (new ViewRenderEventManager)->handleRenderEvent('bagisto.shop.customers.account.orders.view.before', ['order' => $order]);

    app(OrderTimeline::class)->show($manager);

    expect($manager->getTemplates())->toBe([]);
});
