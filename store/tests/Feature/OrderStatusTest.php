<?php

use ClassyFashion\Models\Sales\Order as ClassyOrder;
use Spatie\Activitylog\Models\Activity;
use Webkul\Sales\Models\Order;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function classyWorker(): Admin
{
    return Admin::where('email', 'worker@classy.local')->firstOrFail();
}

it('moves an order through the report statuses', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    $this->actingAs(classyWorker(), 'admin');

    foreach ([
        'confirmed',
        'paid',
        'processing',
        'dispatched',
        'delivered',
    ] as $status) {
        post(route('admin.classy.orders.status.update', $order->id), ['status' => $status])
            ->assertRedirect();

        expect($order->fresh()->status)->toBe($status);
    }

    expect($order->fresh()->status_label)->toBe('Delivered');
});

it('refuses jumps outside the allowed flow', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    $this->actingAs(classyWorker(), 'admin');

    post(route('admin.classy.orders.status.update', $order->id), ['status' => 'delivered'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('pending');
});

it('maps core completed to dispatched when goods ship', function () {
    $order = Order::factory()->create(['status' => 'processing']);

    app(Webkul\Sales\Repositories\OrderRepository::class)->updateOrderStatus($order, 'completed');

    expect($order->fresh()->status)->toBe('dispatched');
});

it('shows the new labels on the admin order view', function () {
    $order = Order::factory()->create(['status' => 'confirmed']);

    $variant = Webkul\Product\Models\Product::whereNotNull('parent_id')->firstOrFail();

    Webkul\Sales\Models\OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $variant->id,
        'sku'        => $variant->sku,
        'name'       => $variant->name,
        'type'       => 'simple',
    ]);

    Webkul\Sales\Models\OrderPayment::factory()->create(['order_id' => $order->id]);

    Webkul\Sales\Models\OrderAddress::factory()->create([
        'order_id'     => $order->id,
        'address_type' => Webkul\Sales\Models\OrderAddress::ADDRESS_TYPE_SHIPPING,
        'first_name'   => 'Delivery',
        'last_name'    => 'Customer',
        'address'      => 'Plot 12 Kampala Road',
        'city'         => 'Kampala',
        'phone'        => '+256772000002',
        'delivery_instructions' => 'Leave at the blue gate, call on arrival',
    ]);

    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    get(route('admin.sales.orders.view', $order->id))
        ->assertOk()
        ->assertSee('Confirmed')
        ->assertSee('Mark as Paid')
        ->assertSee('Leave at the blue gate, call on arrival');
});

it('refuses guests on the status route', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    post(route('admin.classy.orders.status.update', $order->id), ['status' => 'confirmed'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('pending');
});

it('assigns a delivery partner with an audit entry', function () {
    $order = Order::factory()->create(['status' => 'dispatched']);

    $this->actingAs(Admin::where('email', 'worker@classy.local')->firstOrFail(), 'admin');

    post(route('admin.classy.orders.partner.update', $order->id), ['delivery_partner' => 'SafeBoda Rider Kampala'])
        ->assertRedirect();

    expect($order->fresh()->delivery_partner)->toBe('SafeBoda Rider Kampala');

    expect(Activity::where('log_name', 'classy-fashion')
        ->where('event', 'order.delivery')
        ->where('subject_id', $order->id)
        ->count())->toBe(1);
});
