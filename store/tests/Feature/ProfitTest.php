<?php

use ClassyFashion\Support\Profit;
use Database\Seeders\ClassyFashionSeeder;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderItem;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;

// Cost lives in product_attribute_values (EAV), never on products.
function setProductCost(Product $product, float $cost): void
{
    DB::table('product_attribute_values')->updateOrInsert(
        [
            'product_id'   => $product->id,
            'attribute_id' => DB::table('attributes')->where('code', 'cost')->value('id'),
            'locale'       => 'en',
            'channel'      => null,
        ],
        ['float_value' => $cost]
    );
}

it('shows the profit report for a selected period', function () {
    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    $start = now()->subDays(7)->toDateString();
    $end = now()->toDateString();

    get(route('admin.classy.reports.profit', ['start' => $start, 'end' => $end]))
        ->assertOk()
        ->assertSee('Profit Report', false)
        ->assertSee('Total Profit', false);
});

it('exports the profit report as CSV', function () {
    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    $response = get(route('admin.classy.reports.profit', ['export' => 'csv']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('csv')
        ->and($response->streamedContent())->toContain('Date,Transactions');
});

it('keeps the profit report away from the worker and from guests', function () {
    $this->seed(ClassyFashionSeeder::class);

    $this->actingAs(Admin::where('email', 'worker@classy.local')->firstOrFail(), 'admin');

    get(route('admin.classy.reports.profit'))->assertUnauthorized();

    auth()->logout();

    get(route('admin.classy.reports.profit'))->assertRedirect();
});

it('keeps the sale-time cost when the product cost changes later', function () {
    $parent = Product::where('type', 'configurable')->firstOrFail();

    $item = OrderItem::factory()->create([
        'product_id'   => $parent->id,
        'product_type' => Product::class,
        'sku'          => $parent->sku,
        'base_price'   => 50000,
        'price'        => 50000,
        'qty_ordered'  => 1,
        'type'         => 'configurable',
        'cost_price'   => 21000,
    ]);

    $original = (float) $parent->cost;

    // Supplier raises the price after the sale.
    setProductCost($parent, $original + 15000);

    expect(Profit::itemCost($item->fresh()))->toBe(21000.0)
        ->and(Profit::itemProfit($item->fresh()))->toBe(29000.0);

    setProductCost($parent, $original);
});

it('snapshots the cost when an order item is created', function () {
    $parent = Product::where('type', 'configurable')->firstOrFail();

    $item = OrderItem::factory()->create([
        'product_id'   => $parent->id,
        'product_type' => Product::class,
        'sku'          => $parent->sku,
        'base_price'   => 50000,
        'price'        => 50000,
        'qty_ordered'  => 1,
        'type'         => 'configurable',
        'cost_price'   => null,
    ]);

    app(ClassyFashion\Listeners\SnapshotOrderItemCost::class)->handle($item);

    expect((float) $item->fresh()->cost_price)->toBe((float) $parent->cost);
});

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
