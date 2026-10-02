<?php

use Spatie\Activitylog\Models\Activity;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductInventory;
use Webkul\Sales\Models\Order;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('lists low-stock products on the dashboard statistics', function () {
    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    $response = get(route('admin.dashboard.stats', ['type' => 'stock-threshold-products']))
        ->assertOk();

    $stats = $response->json('statistics');

    expect($stats)->toHaveCount(5)
        ->and($stats[0]['name'] ?? null)->not->toBeNull();
});

it('records who changed stock and when', function () {
    $worker = Admin::where('email', 'worker@classy.local')->firstOrFail();

    $this->actingAs($worker, 'admin');

    $variant = Product::whereNotNull('parent_id')->firstOrFail();

    $inventory = ProductInventory::where('product_id', $variant->id)->firstOrFail();

    $old = (int) $inventory->qty;

    app(Webkul\Product\Repositories\ProductInventoryRepository::class)
        ->saveInventories(['inventories' => [$inventory->inventory_source_id => $old + 5]], $variant);

    $entry = Activity::where('log_name', 'classy-fashion')->latest('id')->firstOrFail();

    expect($entry->causer_id)->toBe($worker->id)
        ->and($entry->causer_type)->toContain('Admin')
        ->and($entry->subject_id)->toBe($variant->id)
        ->and($entry->event)->toBe('stock.updated')
        ->and($entry->properties['old_qty'])->toBe($old)
        ->and($entry->properties['new_qty'])->toBe($old + 5)
        ->and($entry->created_at)->not->toBeNull();
});

it('records manual order status changes with the responsible user', function () {
    $worker = Admin::where('email', 'worker@classy.local')->firstOrFail();

    $this->actingAs($worker, 'admin');

    $order = Order::factory()->create(['status' => 'pending']);

    post(route('admin.classy.orders.status.update', $order->id), ['status' => 'confirmed'])
        ->assertRedirect();

    $entry = Activity::where('log_name', 'classy-fashion')
        ->where('event', 'order.status')
        ->latest('id')
        ->firstOrFail();

    expect($entry->causer_id)->toBe($worker->id)
        ->and($entry->subject_id)->toBe($order->id)
        ->and($entry->properties['from'])->toBe('pending')
        ->and($entry->properties['to'])->toBe('confirmed');
});

it('records automatic transitions as system entries', function () {
    $order = Order::factory()->create(['status' => 'processing']);

    app(Webkul\Sales\Repositories\OrderRepository::class)->updateOrderStatus($order, 'completed');

    $entry = Activity::where('log_name', 'classy-fashion')
        ->where('event', 'order.status')
        ->latest('id')
        ->firstOrFail();

    expect($entry->causer_id)->toBeNull()
        ->and($entry->properties['actor'])->toBe('system')
        ->and($entry->properties['to'])->toBe('dispatched');
});

it('raises a low-stock alert when quantity crosses the threshold', function () {
    $worker = Admin::where('email', 'worker@classy.local')->firstOrFail();

    $this->actingAs($worker, 'admin');

    $variant = Product::whereNotNull('parent_id')->firstOrFail();

    $inventory = ProductInventory::where('product_id', $variant->id)->firstOrFail();

    $inventory->update(['qty' => 20]);

    expect(Activity::where('log_name', 'classy-fashion')->where('event', 'stock.low')->count())->toBe(0);

    $inventory->refresh()->update(['qty' => 3]);

    $entry = Activity::where('log_name', 'classy-fashion')->where('event', 'stock.low')->latest('id')->firstOrFail();

    expect($entry->causer_id)->toBe($worker->id)
        ->and($entry->properties['qty'])->toBe(3)
        ->and($entry->properties['threshold'])->toBe(5);
});

it('does not log customer checkouts as staff stock changes', function () {
    $before = Activity::where('log_name', 'classy-fashion')->count();

    $variant = Product::whereNotNull('parent_id')->firstOrFail();

    $inventory = ProductInventory::where('product_id', $variant->id)->firstOrFail();

    $inventory->update(['qty' => (int) $inventory->qty - 1]);

    expect(Activity::where('log_name', 'classy-fashion')->count())->toBe($before);
});
