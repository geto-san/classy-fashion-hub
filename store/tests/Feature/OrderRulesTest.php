<?php

use ClassyFashion\DataGrids\OrderDataGrid;
use ClassyFashion\Models\ManualPayment;
use ClassyFashion\Models\Sales\Order as ClassyOrder;
use Database\Seeders\ClassyFashionSeeder;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderAddress;
use Webkul\Sales\Models\OrderItem;
use Webkul\Sales\Models\OrderPayment;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function rulesOrder(string $status, string $payment = 'cashondelivery', bool $withItem = true): Order
{
    $order = Order::factory()->create(['status' => $status]);

    OrderPayment::factory()->create(['order_id' => $order->id, 'method' => $payment]);

    if ($withItem) {
        $parent = Product::where('type', 'configurable')->firstOrFail();

        OrderItem::factory()->create([
            'order_id'     => $order->id,
            'product_id'   => $parent->id,
            'product_type' => Product::class,
            'sku'          => $parent->sku,
            'name'         => $parent->name,
            'type'         => 'configurable',
            'qty_ordered'  => 1,
            'qty_invoiced' => 0,
            'qty_shipped'  => 0,
            'qty_canceled' => 0,
            'qty_refunded' => 0,
        ]);
    }

    return $order;
}

function rulesAdmin(): Admin
{
    return Admin::where('email', 'admin@example.com')->firstOrFail();
}

it('lets a cash-on-delivery order be packed before it is paid', function () {
    $cod = ClassyOrder::find(rulesOrder('confirmed', 'cashondelivery', false)->id);
    $transfer = ClassyOrder::find(rulesOrder('confirmed', 'moneytransfer', false)->id);

    expect($cod->canTransitionTo('processing'))->toBeTrue()
        ->and($transfer->canTransitionTo('processing'))->toBeFalse()
        ->and($transfer->canTransitionTo('paid'))->toBeTrue();
});

it('never lets staff mark a mobile-money order paid by hand', function () {
    $order = ClassyOrder::find(rulesOrder('pending', 'mobilemoney', false)->id);

    expect($order->canTransitionTo('paid'))->toBeFalse();

    $this->actingAs(rulesAdmin(), 'admin');

    post(route('admin.classy.orders.status.update', $order->id), ['status' => 'paid'])->assertRedirect();

    expect($order->fresh()->status)->toBe('pending');
});

it('records who took the cash when a COD order is delivered, once', function () {
    $order = rulesOrder('dispatched', 'cashondelivery', false);

    $this->actingAs(rulesAdmin(), 'admin');

    post(route('admin.classy.orders.status.update', $order->id), ['status' => 'delivered'])->assertRedirect();

    $payments = ManualPayment::where('order_id', $order->id)->get();

    expect($order->fresh()->status)->toBe('delivered')
        ->and($payments)->toHaveCount(1)
        ->and($payments->first()->method)->toBe('cashondelivery')
        ->and($payments->first()->received_by)->toBe(rulesAdmin()->id);
});

it('records a payment when staff mark an order paid', function () {
    $order = rulesOrder('confirmed', 'moneytransfer', false);

    $this->actingAs(rulesAdmin(), 'admin');

    post(route('admin.classy.orders.status.update', $order->id), ['status' => 'paid'])->assertRedirect();

    expect(ManualPayment::where('order_id', $order->id)->where('method', 'moneytransfer')->count())->toBe(1);
});

it('blocks core invoicing until the order is paid', function () {
    $pending = rulesOrder('pending', 'moneytransfer');
    $paid = rulesOrder('paid', 'moneytransfer');

    // Without the guard core would allow both.
    expect(Order::query()->find($pending->id)->canInvoice())->toBeTrue();

    expect(ClassyOrder::find($pending->id)->canInvoice())->toBeFalse()
        ->and(ClassyOrder::find($paid->id)->canInvoice())->toBeTrue();
});

it('stops customers cancelling a paid order but not staff', function () {
    $paid = ClassyOrder::find(rulesOrder('paid', 'mobilemoney')->id);
    $pending = ClassyOrder::find(rulesOrder('pending', 'cashondelivery')->id);

    expect($paid->canCancel())->toBeFalse()
        ->and($pending->canCancel())->toBeTrue();

    $this->actingAs(rulesAdmin(), 'admin');

    expect(ClassyOrder::find($paid->id)->canCancel())->toBeTrue();
});

it('lets staff cancel a paid mobile-money order and flags the refund', function () {
    $order = rulesOrder('paid', 'mobilemoney');

    $this->actingAs(rulesAdmin(), 'admin');

    post(route('admin.classy.orders.status.update', $order->id), ['status' => 'canceled'])
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect($order->fresh()->status)->toBe('canceled');
});

it('renders and filters every report status in the admin order list', function () {
    $grid = app(Webkul\Admin\DataGrids\Sales\OrderDataGrid::class);

    expect($grid)->toBeInstanceOf(OrderDataGrid::class);

    $grid->prepareColumns();

    $status = collect($grid->getColumns())->first(fn ($column) => $column->getIndex() === 'status');

    $values = array_column($status->getFilterableOptions(), 'value');

    expect($values)->toContain('confirmed', 'paid', 'dispatched', 'delivered')
        ->and($values)->not->toContain('fraud', 'closed', 'completed');

    foreach (['confirmed' => 'Confirmed', 'paid' => 'Paid', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered'] as $code => $label) {
        expect(($status->getClosure())((object) ['status' => $code]))->toContain($label);
    }
});

it('shows cost and profit to the owner but not to the worker', function () {
    $this->seed(ClassyFashionSeeder::class);

    $order = rulesOrder('confirmed', 'cashondelivery');

    OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'address_type' => OrderAddress::ADDRESS_TYPE_SHIPPING,
    ]);

    $this->actingAs(rulesAdmin(), 'admin');

    get(route('admin.sales.orders.view', $order->id))->assertOk()->assertSee('Order Profit');

    auth('admin')->logout();

    $this->actingAs(Admin::where('email', 'worker@classy.local')->firstOrFail(), 'admin');

    get(route('admin.sales.orders.view', $order->id))->assertOk()->assertDontSee('Order Profit');
});
