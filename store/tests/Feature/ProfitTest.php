<?php

use ClassyFashion\Support\Profit;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderItem;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;

it('computes profit per item from variant cost with parent fallback', function () {
    $parent = Product::where('type', 'configurable')->firstOrFail();

    $variant = $parent->variants->firstOrFail();

    $item = OrderItem::factory()->create([
        'product_id'   => $variant->id,
        'product_type' => Product::class,
        'sku'          => $variant->sku,
        'base_price'   => 45000,
        'price'        => 45000,
        'qty_ordered'  => 2,
        'type'         => 'simple',
    ]);

    expect(Profit::itemCost($item))->toBe((float) $parent->cost)
        ->and(Profit::itemProfit($item))->toBe((45000 - (float) $parent->cost) * 2);
});

it('sums order profit across items', function () {
    $order = Order::factory()->create(['status' => 'paid']);

    $parent = Product::where('type', 'configurable')->firstOrFail();

    OrderItem::factory()->create([
        'order_id'     => $order->id,
        'product_id'   => $parent->id,
        'product_type' => Product::class,
        'sku'          => $parent->sku,
        'base_price'   => (float) $parent->price,
        'price'        => (float) $parent->price,
        'qty_ordered'  => 1,
        'type'         => 'configurable',
    ]);

    $expected = ((float) $parent->price - (float) $parent->cost) * 1;

    expect(Profit::orderProfit($order->fresh()))->toBe($expected);
});

it('shows profit on the admin order view', function () {
    $order = Order::factory()->create(['status' => 'paid']);

    $parent = Product::where('type', 'configurable')->firstOrFail();

    $variant = $parent->variants->firstOrFail();

    $parentItem = OrderItem::factory()->create([
        'order_id'     => $order->id,
        'product_id'   => $parent->id,
        'product_type' => Product::class,
        'sku'          => $parent->sku,
        'name'         => $parent->name,
        'base_price'   => (float) $parent->price,
        'price'        => (float) $parent->price,
        'qty_ordered'  => 1,
        'type'         => 'configurable',
    ]);

    OrderItem::factory()->create([
        'order_id'     => $order->id,
        'parent_id'    => $parentItem->id,
        'product_id'   => $variant->id,
        'product_type' => Product::class,
        'sku'          => $variant->sku,
        'name'         => $variant->name,
        'base_price'   => (float) $parent->price,
        'price'        => (float) $parent->price,
        'qty_ordered'  => 1,
        'type'         => 'simple',
    ]);

    Webkul\Sales\Models\OrderPayment::factory()->create(['order_id' => $order->id]);

    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    $profit = core()->formatBasePrice(((float) $parent->price - (float) $parent->cost));

    get(route('admin.sales.orders.view', $order->id))
        ->assertOk()
        ->assertSee('Order Profit', false)
        ->assertSee($profit, false);
});
