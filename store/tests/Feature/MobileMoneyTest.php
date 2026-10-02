<?php

use ClassyFashion\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Webkul\Checkout\Models\Cart;
use Webkul\Customer\Models\Customer;
use Webkul\Customer\Models\CustomerAddress;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

function mobilemoneyCustomer(): Customer
{
    return Customer::where('email', 'customer@classy.local')->firstOrFail();
}

function mobilemoneyCart(): Cart
{
    $customer = mobilemoneyCustomer();

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

    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id])->toArray();
    $address['address'] = [fake()->address()];

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing'  => [...$address, 'use_for_shipping' => false],
        'shipping' => [...$address],
    ])->assertOk();

    postJson(route('shop.checkout.onepage.shipping_methods.store'), [
        'shipping_method' => 'flatrate_flatrate',
    ])->assertOk();

    postJson(route('shop.checkout.onepage.payment_methods.store'), [
        'payment' => ['method' => 'mobilemoney'],
    ])->assertOk();

    return Cart::where('customer_id', $customer->id)->latest('id')->firstOrFail();
}

function mobilemoneyKeys(): void
{
    putenv('FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-xxx');
    putenv('FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-xxx');
    putenv('FLUTTERWAVE_SECRET_HASH=classy-test-hash');
}

beforeEach(function () {
    $this->actingAs(mobilemoneyCustomer());

    mobilemoneyKeys();
});

it('hides the method until gateway keys exist', function () {
    putenv('FLUTTERWAVE_SECRET_KEY=');
    putenv('FLUTTERWAVE_PUBLIC_KEY=');

    $method = app(ClassyFashion\Payment\MobileMoney::class);

    expect($method->isAvailable())->toBeFalse();

    mobilemoneyKeys();

    mobilemoneyCart();

    expect($method->isAvailable())->toBeTrue();
});

it('starts a pending attempt on charge', function () {
    Http::fake([
        'api.flutterwave.com/v3/charges*' => Http::response([
            'status' => 'success',
            'data'   => ['id' => 999001, 'tx_ref' => 'CFH-X', 'status' => 'pending'],
        ]),
    ]);

    $cart = mobilemoneyCart();

    $ordersBefore = Order::where('customer_id', $cart->customer_id)->count();

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '+256772000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    expect($attempt->status)->toBe('pending')
        ->and($attempt->gateway_tx_id)->toBe('999001')
        ->and($attempt->network)->toBe('MTN')
        ->and(Order::where('customer_id', $cart->customer_id)->count())->toBe($ordersBefore);
});

it('creates the paid order only after verified webhook success', function () {
    Http::fake([
        'api.flutterwave.com/v3/charges*' => Http::response([
            'status' => 'success',
            'data'   => ['id' => 999002, 'status' => 'pending'],
        ]),
    ]);

    $cart = mobilemoneyCart();

    post(route('classy.mobilemoney.charge'), ['network' => 'AIRTEL', 'phone' => '+256752000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    Http::fake([
        'api.flutterwave.com/v3/transactions/999002/verify' => Http::response([
            'status' => 'success',
            'data'   => [
                'id'       => 999002,
                'tx_ref'   => $attempt->tx_ref,
                'status'   => 'successful',
                'amount'   => (float) $attempt->amount,
                'currency' => 'UGX',
            ],
        ]),
    ]);

    postJson(route('classy.mobilemoney.webhook'), [
        'event' => 'charge.completed',
        'data'  => ['id' => 999002, 'tx_ref' => $attempt->tx_ref],
    ], ['verif-hash' => 'classy-test-hash'])
        ->assertOk();

    $attempt->refresh();

    $order = Order::findOrFail($attempt->order_id);

    expect($attempt->status)->toBe('success')
        ->and($order->status)->toBe('paid')
        ->and($order->order_currency_code)->toBe('UGX');

    expect(Activity::where('log_name', 'classy-fashion')
        ->where('event', 'order.payment')
        ->where('subject_id', $order->id)
        ->count())->toBe(1);
});

it('rejects webhooks with a bad signature', function () {
    $attempt = PaymentAttempt::create([
        'cart_id' => 1, 'tx_ref' => 'CFH-FORGED-1', 'amount' => 45000,
        'currency' => 'UGX', 'status' => 'pending',
    ]);

    postJson(route('classy.mobilemoney.webhook'), [
        'event' => 'charge.completed',
        'data'  => ['id' => 1, 'tx_ref' => 'CFH-FORGED-1'],
    ], ['verif-hash' => 'wrong-hash'])
        ->assertForbidden();

    expect($attempt->fresh()->status)->toBe('pending');
});

it('creates no order on amount mismatch', function () {
    Http::fake([
        'api.flutterwave.com/v3/transactions/999003/verify' => Http::response([
            'status' => 'success',
            'data'   => ['id' => 999003, 'tx_ref' => 'CFH-MISMATCH-1', 'status' => 'successful', 'amount' => 100, 'currency' => 'UGX'],
        ]),
    ]);

    $attempt = PaymentAttempt::create([
        'cart_id' => 1, 'tx_ref' => 'CFH-MISMATCH-1', 'amount' => 45000,
        'currency' => 'UGX', 'gateway_tx_id' => '999003', 'status' => 'pending',
    ]);

    postJson(route('classy.mobilemoney.webhook'), [
        'event' => 'charge.completed',
        'data'  => ['id' => 999003, 'tx_ref' => 'CFH-MISMATCH-1'],
    ], ['verif-hash' => 'classy-test-hash'])
        ->assertOk();

    expect($attempt->fresh()->status)->toBe('pending')
        ->and($attempt->fresh()->order_id)->toBeNull();
});

it('proves returning from payment creates no order', function () {
    Http::fake([
        'api.flutterwave.com/v3/charges*' => Http::response([
            'status' => 'success',
            'data'   => ['id' => 999004, 'status' => 'pending'],
        ]),
        'api.flutterwave.com/v3/transactions/999004/verify' => Http::response([
            'status' => 'success',
            'data'   => ['id' => 999004, 'status' => 'pending'],
        ]),
    ]);

    $cart = mobilemoneyCart();

    $ordersBefore = Order::where('customer_id', $cart->customer_id)->count();

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '+256772000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    get(route('classy.mobilemoney.return', ['attempt' => $attempt->id]))
        ->assertRedirect(route('classy.mobilemoney.status', ['attempt' => $attempt->id]));

    get(route('classy.mobilemoney.status', ['attempt' => $attempt->id]))
        ->assertOk();

    expect($attempt->fresh()->status)->toBe('pending')
        ->and(Order::where('customer_id', $cart->customer_id)->count())->toBe($ordersBefore);
});
