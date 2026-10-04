<?php

use Webkul\Checkout\Models\Cart;
use Webkul\Customer\Models\Customer;
use Webkul\Customer\Models\CustomerAddress;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

it('shows the delivery instructions field on the shipping form', function () {
    $customer = Customer::where('email', 'customer@classy.local')->firstOrFail();

    $this->actingAs($customer);

    $product = Product::where('type', 'configurable')->with('variants')->firstOrFail();

    $variant = $product->variants->firstOrFail();

    $colorId = DB::table('attributes')->where('code', 'color')->value('id');
    $sizeId = DB::table('attributes')->where('code', 'size')->value('id');

    postJson(route('shop.api.checkout.cart.store'), [
        'selected_configurable_option' => $variant->id,
        'product_id'                  => $product->id,
        'quantity'                    => '1',
        'super_attribute'             => [$colorId => (string) $variant->color, $sizeId => (string) $variant->size],
    ])->assertOk();

    get(route('shop.checkout.onepage.index'))
        ->assertOk()
        ->assertSee('delivery_instructions', false);
});

/**
 * End-to-end customer order on the real Classy Fashion catalog (Gap #4).
 *
 * Customer adds a configurable variant to cart, checks out with Cash on
 * Delivery + Flat Rate shipping, places the order and sees it in history.
 */
it('places a configurable fashion order end to end', function () {
    // Arrange: seeded catalog + customer (no truncate-safe seeding here).
    $customer = Customer::where('email', 'customer@classy.local')->firstOrFail();

    $maxOrderId = Order::max('id') ?? 0;

    $this->actingAs($customer);

    $product = Product::where('type', 'configurable')->with('variants')->firstOrFail();

    $variant = $product->variants->firstOrFail();

    $colorId = DB::table('attributes')->where('code', 'color')->value('id');
    $sizeId = DB::table('attributes')->where('code', 'size')->value('id');

    $superAttribute = [$colorId => (string) $variant->color, $sizeId => (string) $variant->size];

    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id])->toArray();
    $address['address'] = [fake()->address()];

    // Act 1: add variant to cart through the storefront API.
    postJson(route('shop.api.checkout.cart.store'), [
        'selected_configurable_option' => $variant->id,
        'product_id'                  => $product->id,
        'is_buy_now'                  => '0',
        'rating'                      => '0',
        'quantity'                    => '1',
        'super_attribute'             => $superAttribute,
    ])->assertOk();

    // Act 2: checkout addresses (delivery location + contact live here).
    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing'  => [...$address, 'use_for_shipping' => false],
        'shipping' => [
            ...$address,
            'first_name'            => 'Delivery',
            'phone'                 => '+256772000002',
            'delivery_instructions' => 'Leave at the blue gate, call on arrival',
        ],
    ])->assertOk();

    // Act 3: flat-rate shipping + cash on delivery.
    postJson(route('shop.checkout.onepage.shipping_methods.store'), [
        'shipping_method' => 'flatrate_flatrate',
    ])->assertOk();

    postJson(route('shop.checkout.onepage.payment_methods.store'), [
        'payment' => ['method' => 'cashondelivery'],
    ])->assertOk();

    // Act 4: place the order.
    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertOk()
        ->assertJsonPath('data.redirect_url', route('shop.checkout.onepage.success'));

    // Assert: pending UGX order with the variant, visible in history.
    $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

    expect($order->id)->toBeGreaterThan($maxOrderId);

    expect($order->status)->toBe('pending')
        ->and($order->order_currency_code)->toBe('UGX')
        ->and((float) $order->grand_total)->toBeGreaterThan(0)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_id)->toBe($product->id)
        ->and((float) $order->items->first()->price)->toBe((float) $variant->price)
        ->and(json_encode($order->items->first()->additional))->toContain((string) $variant->id);

    // The history page hydrates rows from the same route over AJAX.
    get(route('shop.customers.account.orders.index'))->assertOk();

    $grid = get(
        route('shop.customers.account.orders.index'),
        ['X-Requested-With' => 'XMLHttpRequest']
    )->assertOk();

    expect(json_encode($grid->json()))->toContain((string) $order->increment_id);

    // Delivery info (report 9.7) reached the order address.
    expect($order->shipping_address->delivery_instructions)
        ->toBe('Leave at the blue gate, call on arrival');
});
