<?php

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Payment\Momo;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Webkul\Checkout\Models\Cart;
use Webkul\Customer\Models\Customer;
use Webkul\Customer\Models\CustomerAddress;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;
use Webkul\User\Models\Admin;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

function mtnCustomer(): Customer
{
    return Customer::where('email', 'customer@classy.local')->firstOrFail();
}

function mtnCart(): Cart
{
    $customer = mtnCustomer();

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

function mtnKeys(): void
{
    config([
        'classy.momo.provider'              => 'auto',
        'classy.momo.till_number'           => '0789001234',
        'classy.momo.mtn.subscription_key'  => 'test-sub-key',
        'classy.momo.mtn.api_user_id'       => 'test-user-id',
        'classy.momo.mtn.api_key'           => 'test-api-key',
        'classy.momo.mtn.base_url'          => 'https://sandbox.momodeveloper.mtn.com',
        'classy.momo.mtn.environment'       => 'sandbox',
        'classy.momo.mtn.currency'          => 'UGX',
    ]);
}

function mtnToken(): void
{
    Http::fake([
        'sandbox.momodeveloper.mtn.com/collection/token*' => Http::response([
            'access_token' => 'test-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600,
        ]),
    ]);
}

beforeEach(function () {
    $this->actingAs(mtnCustomer());
});

it('charges MTN and finalises on a successful status query', function () {
    mtnKeys();

    $cart = mtnCart();

    $txRef = null;

    Http::fake([
        'sandbox.momodeveloper.mtn.com/collection/token*' => Http::response([
            'access_token' => 'test-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600,
        ]),
        // POST = charge (202 accepted); GET = status query (successful).
        // One stub: Http::fake appends, so re-faking would never take effect.
        'sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay*' => function ($request) use ($cart) {
            if ($request->method() === 'POST') {
                return Http::response('', 202);
            }

            return Http::response([
                'status' => 'SUCCESSFUL', 'externalId' => $cart->fresh()->id ? PaymentAttempt::where('cart_id', $cart->id)->firstOrFail()->tx_ref : '',
                'amount' => (string) (float) $cart->grand_total,
            ]);
        },
    ]);

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '+256772000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    expect($attempt->provider)->toBe('mtn')
        ->and($attempt->gateway_tx_id)->not->toBeNull();

    get(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]))
        ->assertRedirect(route('shop.checkout.onepage.success'));

    expect($attempt->fresh()->status)->toBe('success')
        ->and(Order::find($attempt->fresh()->order_id)->status)->toBe('paid');
});

it('accepts the MTN callback and verifies server-side', function () {
    mtnKeys();
    mtnToken();

    $cart = mtnCart();

    $attempt = PaymentAttempt::create([
        'cart_id' => $cart->id, 'tx_ref' => 'CFH-MTN-CB-1', 'amount' => $cart->grand_total,
        'currency' => 'UGX', 'network' => 'MTN', 'provider' => 'mtn',
        'gateway_tx_id' => 'mtn-ref-1', 'status' => 'pending',
    ]);

    session()->push('classy.mobilemoney.attempts', $attempt->public_id);

    Http::fake([
        'sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay/*' => Http::response([
            'status' => 'SUCCESSFUL', 'externalId' => 'CFH-MTN-CB-1', 'amount' => (string) (float) $attempt->amount,
        ]),
    ]);

    postJson(route('classy.mobilemoney.mtn-callback'), ['externalId' => 'CFH-MTN-CB-1'])
        ->assertOk()
        ->assertJsonPath('message', 'success');

    expect($attempt->fresh()->status)->toBe('success');
});

it('runs the manual Till flow with zero gateway keys', function () {
    config([
        'classy.momo.till_number'       => '0789001234',
        'classy.momo.mtn.subscription_key' => '',
    ]);

    expect(Momo::provider('MTN'))->toBeNull()
        ->and(Momo::manualEnabled())->toBeTrue();

    $cart = mtnCart();

    post(route('classy.mobilemoney.charge'), ['network' => 'AIRTEL', 'phone' => '+256752000002'])
        ->assertRedirect();

    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    expect($attempt->provider)->toBeNull()
        ->and($attempt->gateway_tx_id)->toBeNull();

    // Till instructions shown, no order yet.
    get(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]))
        ->assertOk()
        ->assertSee('0789001234', false);

    // Customer submits their SMS transaction ID.
    post(route('classy.mobilemoney.claim', ['attempt' => $attempt->public_id]), ['customer_claim' => 'MPESA123ABC'])
        ->assertRedirect();

    expect($attempt->fresh()->customer_claim)->toBe('MPESA123ABC');

    $ordersBefore = Order::where('customer_id', $cart->customer_id)->count();

    // Staff confirms after checking the MTN app: paid order appears.
    $this->actingAs(Admin::where('email', 'admin@example.com')->firstOrFail(), 'admin');

    post(route('admin.classy.payments.confirm', $attempt->public_id))
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe('success')
        ->and(Order::where('customer_id', $cart->customer_id)->count())->toBe($ordersBefore + 1);

    $order = Order::find($attempt->fresh()->order_id);

    expect($order->status)->toBe('paid');

    expect(Activity::where('log_name', 'classy-fashion')
        ->where('event', 'order.payment')
        ->where('subject_id', $order->id)
        ->count())->toBe(1);
});
