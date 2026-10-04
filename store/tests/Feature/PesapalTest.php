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

function pesapalKeys(): void
{
    config([
        'classy.momo.provider'              => 'auto',
        'classy.momo.pesapal.consumer_key'    => 'test-consumer-key',
        'classy.momo.pesapal.consumer_secret' => 'test-consumer-secret',
        'classy.momo.pesapal.sandbox'         => true,
        'classy.momo.pesapal.ipn_id'          => '11111111-2222-4333-8444-555555555555',
        'classy.momo.mtn.subscription_key'  => '',
        'classy.flutterwave.secret_key'     => '',
        'classy.flutterwave.public_key'     => '',
    ]);
}

function pesapalCustomer(): Customer
{
    return Customer::where('email', 'customer@classy.local')->firstOrFail();
}

function pesapalCart(): Cart
{
    $customer = pesapalCustomer();

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

function pesapalFakes(string $txRef, float $amount, string $tracking = 'track-001'): void
{
    Http::fake([
        'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
            'token' => 'test-pesapal-token', 'expiryDate' => now()->addMinutes(5)->toIso8601String(),
            'status' => '200',
        ]),
        'cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
            'order_tracking_id' => $tracking,
            'merchant_reference' => $txRef,
            'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/test',
            'status' => '200',
        ]),
        'cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus*' => Http::response([
            'payment_method' => 'MTN', 'amount' => $amount, 'merchant_reference' => $txRef,
            'payment_status_description' => 'COMPLETED', 'status_code' => 1, 'currency' => 'UGX', 'status' => '200',
        ]),
    ]);
}

beforeEach(function () {
    $this->actingAs(pesapalCustomer());
});

it('sends the customer to Pesapal and stores the tracking id', function () {
    pesapalKeys();

    $cart = pesapalCart();

    Http::fake([
        'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 't', 'status' => '200']),
        'cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
            'order_tracking_id' => 'track-abc', 'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/test', 'status' => '200',
        ]),
    ]);

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '+256772000002'])
        ->assertRedirect('https://cybqa.pesapal.com/pesapaliframe/test');

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    expect($attempt->provider)->toBe('pesapal')
        ->and($attempt->gateway_tx_id)->toBe('track-abc')
        ->and(Order::where('customer_id', $cart->customer_id)->count())
        ->toBe(Order::where('customer_id', $cart->customer_id)->count());
});

it('creates the paid order after the Pesapal callback verifies', function () {
    pesapalKeys();

    $cart = pesapalCart();
    $before = Order::where('customer_id', $cart->customer_id)->count();

    Http::fake([
        'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 't', 'status' => '200']),
        'cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
            'order_tracking_id' => 'track-pay', 'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/test', 'status' => '200',
        ]),
    ]);

    post(route('classy.mobilemoney.charge'), ['network' => 'AIRTEL', 'phone' => '+256752000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    pesapalFakes($attempt->tx_ref, (float) $attempt->amount, 'track-pay');

    // Customer comes back from Pesapal: sent to the status page, verified.
    get(route('classy.mobilemoney.pesapal-return', [
        'OrderTrackingId' => 'track-pay',
        'OrderMerchantReference' => $attempt->tx_ref,
        'OrderNotificationType' => 'CALLBACKURL',
    ]))->assertRedirect(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]));

    get(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]))
        ->assertRedirect(route('shop.checkout.onepage.success'));

    expect($attempt->fresh()->status)->toBe('success')
        ->and(Order::where('customer_id', $cart->customer_id)->count())->toBe($before + 1);

    $order = Order::find($attempt->fresh()->order_id);

    expect($order->status)->toBe('paid');
});

it('answers the Pesapal IPN in its own shape', function () {
    pesapalKeys();

    $cart = pesapalCart();

    Http::fake([
        'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 't', 'status' => '200']),
        'cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
            'order_tracking_id' => 'track-ipn', 'redirect_url' => 'https://cybqa.pesapal.com/x', 'status' => '200',
        ]),
    ]);

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '+256772000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    pesapalFakes($attempt->tx_ref, (float) $attempt->amount, 'track-ipn');

    postJson(route('classy.mobilemoney.pesapal-ipn'), [
        'OrderTrackingId' => 'track-ipn',
        'OrderMerchantReference' => $attempt->tx_ref,
        'OrderNotificationType' => 'IPNCHANGE',
    ])->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonPath('orderTrackingId', 'track-ipn');

    expect($attempt->fresh()->status)->toBe('success');

    // Unknown references get the 500 shape and no order.
    postJson(route('classy.mobilemoney.pesapal-ipn'), [
        'OrderTrackingId' => 'track-unknown',
        'OrderMerchantReference' => 'CFH-NOPE',
        'OrderNotificationType' => 'IPNCHANGE',
    ])->assertOk()
        ->assertJsonPath('status', 500);
});

it('creates no order when Pesapal reports a different amount', function () {
    pesapalKeys();

    $cart = pesapalCart();
    $before = Order::where('customer_id', $cart->customer_id)->count();

    Http::fake([
        'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 't', 'status' => '200']),
        'cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
            'order_tracking_id' => 'track-diff', 'redirect_url' => 'https://cybqa.pesapal.com/x', 'status' => '200',
        ]),
        'cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus*' => Http::response([
            'payment_method' => 'MTN', 'amount' => 100, 'merchant_reference' => 'whatever',
            'payment_status_description' => 'COMPLETED', 'status_code' => 1, 'currency' => 'UGX', 'status' => '200',
        ]),
    ]);

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '+256772000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    get(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]))
        ->assertOk();

    expect($attempt->fresh()->status)->toBe('pending')
        ->and(Order::where('customer_id', $cart->customer_id)->count())->toBe($before);
});
