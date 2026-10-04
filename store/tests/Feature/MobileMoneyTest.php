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
    // Settings are read from config('classy.flutterwave'), never env().
    // MTN is cleared so local .env sandbox keys cannot leak into tests.
    config([
        'classy.flutterwave.public_key'  => 'FLWPUBK_TEST-xxx',
        'classy.flutterwave.secret_key'  => 'FLWSECK_TEST-xxx',
        'classy.flutterwave.secret_hash' => 'classy-test-hash',
        'classy.flutterwave.sandbox'     => true,
        'classy.momo.provider'           => 'auto',
        'classy.momo.mtn.subscription_key' => '',
        'classy.momo.mtn.api_user_id'      => '',
        'classy.momo.mtn.api_key'          => '',
        'classy.momo.till_number'          => '',
    ]);
}

beforeEach(function () {
    $this->actingAs(mobilemoneyCustomer());

    mobilemoneyKeys();
});

it('hides the method until gateway keys exist', function () {
    config([
        'classy.flutterwave.secret_key' => '',
        'classy.flutterwave.public_key' => '',
    ]);

    $method = app(ClassyFashion\Payment\MobileMoney::class);

    expect($method->isAvailable())->toBeFalse();

    mobilemoneyKeys();

    mobilemoneyCart();

    expect($method->isAvailable())->toBeTrue();
});

it('stays hidden when the keys do not match the sandbox/live mode', function () {
    mobilemoneyCart();

    config(['classy.flutterwave.sandbox' => false]); // test keys, live mode

    expect(app(ClassyFashion\Payment\MobileMoney::class)->isAvailable())->toBeFalse();

    config(['classy.flutterwave.sandbox' => true]);

    expect(app(ClassyFashion\Payment\MobileMoney::class)->isAvailable())->toBeTrue();
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

    $this->withSession(['classy.mobilemoney.attempts' => [$attempt->public_id]]);

    get(route('classy.mobilemoney.return', ['attempt' => $attempt->public_id]))
        ->assertRedirect(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]));

    get(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]))
        ->assertOk();

    expect($attempt->fresh()->status)->toBe('pending')
        ->and(Order::where('customer_id', $cart->customer_id)->count())->toBe($ordersBefore);
});

/**
 * Start a charge for a fresh cart and return the pending attempt.
 */
function mobilemoneyAttempt(int $gatewayId, string $network = 'MTN'): PaymentAttempt
{
    Http::fake([
        'api.flutterwave.com/v3/charges*' => Http::response([
            'status' => 'success',
            'data'   => ['id' => $gatewayId, 'status' => 'pending'],
        ]),
    ]);

    $cart = mobilemoneyCart();

    post(route('classy.mobilemoney.charge'), ['network' => $network, 'phone' => '+256772000002'])
        ->assertRedirect();

    return PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();
}

function mobilemoneyVerified(PaymentAttempt $attempt, int $gatewayId): void
{
    Http::fake([
        "api.flutterwave.com/v3/transactions/{$gatewayId}/verify" => Http::response([
            'status' => 'success',
            'data'   => [
                'id'       => $gatewayId,
                'tx_ref'   => $attempt->tx_ref,
                'status'   => 'successful',
                'amount'   => (float) $attempt->amount,
                'currency' => 'UGX',
            ],
        ]),
    ]);
}

function mobilemoneyWebhook(PaymentAttempt $attempt, int $gatewayId)
{
    return postJson(route('classy.mobilemoney.webhook'), [
        'event' => 'charge.completed',
        'data'  => ['id' => $gatewayId, 'tx_ref' => $attempt->tx_ref],
    ], ['verif-hash' => 'classy-test-hash']);
}

it('uses an unguessable id in payment URLs and sends a normalised number', function () {
    $attempt = mobilemoneyAttempt(999010);

    expect($attempt->public_id)->toMatch('/^[0-9a-f-]{36}$/');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'mobile_money_uganda')
        && $request['phone_number'] === '256772000002'
        && $request['redirect_url'] === route('classy.mobilemoney.return', ['attempt' => $attempt->public_id]));
});

it('rejects numbers that are not Ugandan mobile money numbers', function () {
    mobilemoneyCart();

    $before = PaymentAttempt::count();

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '12345'])
        ->assertSessionHasErrors('phone');

    expect(PaymentAttempt::count())->toBe($before);
});

it('creates exactly one order when the webhook and the status check race', function () {
    $attempt = mobilemoneyAttempt(999011);

    mobilemoneyVerified($attempt, 999011);

    $ordersBefore = Order::count();

    mobilemoneyWebhook($attempt, 999011)->assertOk();

    // Second delivery of the same event (gateways retry) and a customer poll.
    mobilemoneyWebhook($attempt, 999011)->assertOk();

    $this->withSession(['classy.mobilemoney.attempts' => [$attempt->public_id]])
        ->get(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]))
        ->assertRedirect(route('shop.checkout.onepage.success'));

    expect(Order::count())->toBe($ordersBefore + 1)
        ->and(PaymentAttempt::where('order_id', $attempt->fresh()->order_id)->count())->toBe(1);
});

it('refuses to finalise an attempt that another request already claimed', function () {
    $attempt = mobilemoneyAttempt(999012);

    mobilemoneyVerified($attempt, 999012);

    // Simulate the other request having won the atomic claim.
    $attempt->update(['status' => PaymentAttempt::STATUS_FINALIZING]);

    $ordersBefore = Order::count();

    mobilemoneyWebhook($attempt, 999012)->assertOk();

    expect(Order::count())->toBe($ordersBefore)
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_FINALIZING);
});

it('hides another shopper\'s payment attempt', function () {
    $attempt = mobilemoneyAttempt(999013);

    // No session ownership (a different browser): not found, nothing finalised.
    $this->flushSession();

    get(route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]))->assertNotFound();
    get(route('classy.mobilemoney.return', ['attempt' => $attempt->public_id]))->assertNotFound();

    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PENDING);
});

it('records a verified payment with no order as paid_unfulfilled, not failed', function () {
    $attempt = mobilemoneyAttempt(999014);

    mobilemoneyVerified($attempt, 999014);

    // The cart disappears between paying and finalising.
    Cart::where('id', $attempt->cart_id)->delete();

    mobilemoneyWebhook($attempt, 999014)->assertOk();

    $attempt->refresh();

    expect($attempt->status)->toBe(PaymentAttempt::STATUS_PAID_UNFULFILLED)
        ->and($attempt->order_id)->toBeNull()
        ->and($attempt->needsAttention())->toBeTrue();

    expect(Activity::where('log_name', 'classy-fashion')
        ->where('event', 'order.payment_unfulfilled')
        ->count())->toBeGreaterThanOrEqual(1);
});

it('expires an unapproved prompt but still honours a late payment', function () {
    $attempt = mobilemoneyAttempt(999015);

    $attempt->update(['expires_at' => now()->subMinute()]);

    // One stateful stub serving pending then successful: Http::fake appends
    // (never replaces), so re-faking the same URL would keep serving the
    // first response, and a nested fakeSequence registers a catch-all '*'.
    $verifyCalls = 0;
    $txRef = $attempt->tx_ref;
    $amount = (float) $attempt->amount;

    Http::fake([
        'api.flutterwave.com/v3/transactions/999015/verify' => function () use (&$verifyCalls, $txRef, $amount) {
            $verifyCalls++;

            return $verifyCalls === 1
                ? Http::response(['status' => 'success', 'data' => ['id' => 999015, 'status' => 'pending']])
                : Http::response(['status' => 'success', 'data' => [
                    'id'       => 999015,
                    'tx_ref'   => $txRef,
                    'status'   => 'successful',
                    'amount'   => $amount,
                    'currency' => 'UGX',
                ]]);
        },
    ]);

    mobilemoneyWebhook($attempt, 999015)->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_EXPIRED);

    // The customer approves on their phone after the timeout.
    mobilemoneyWebhook($attempt, 999015)->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_SUCCESS)
        ->and($attempt->fresh()->order_id)->not->toBeNull();
});

it('expires stale pending attempts from the command', function () {
    $stale = PaymentAttempt::create([
        'cart_id' => 1, 'tx_ref' => 'CFH-STALE-1', 'amount' => 1000, 'currency' => 'UGX',
        'status' => 'pending', 'expires_at' => now()->subHour(),
    ]);

    $fresh = PaymentAttempt::create([
        'cart_id' => 1, 'tx_ref' => 'CFH-FRESH-1', 'amount' => 1000, 'currency' => 'UGX',
        'status' => 'pending',
    ]);

    $this->artisan('classy:expire-payments')->assertSuccessful();

    expect($stale->fresh()->status)->toBe('expired')
        ->and($fresh->fresh()->status)->toBe('pending');
});
