<?php

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Payment\CourseworkProvider;
use ClassyFashion\Payment\Momo;
use ClassyFashion\Repositories\PaymentAttemptRepository;
use ClassyFashion\Support\AiAssistant;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Customer\Repositories\CustomerAddressRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Sales\Repositories\OrderRepository;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/**
 * Build a real checkout through the same endpoints used by the storefront.
 */
function courseworkCart(): mixed
{
    $customer = app(CustomerRepository::class)->findOneByField('email', 'customer@classy.local');
    test()->actingAs($customer);
    $product = app(ProductRepository::class)->getModel()->where('type', 'configurable')->with('variants')->firstOrFail();
    $variant = $product->variants->first();
    $attributes = app(AttributeRepository::class);

    postJson(route('shop.api.checkout.cart.store'), [
        'product_id' => $product->id,
        'selected_configurable_option' => $variant->id,
        'quantity' => 1,
        'super_attribute' => [
            $attributes->findOneByField('code', 'color')->id => (string) $variant->color,
            $attributes->findOneByField('code', 'size')->id => (string) $variant->size,
        ],
    ])->assertOk();

    $address = app(CustomerAddressRepository::class)->create([
        'customer_id' => $customer->id,
        'first_name' => 'Coursework',
        'last_name' => 'Customer',
        'email' => $customer->email,
        'address' => 'University Road',
        'country' => 'UG',
        'state' => 'Western',
        'city' => 'Mbarara',
        'postcode' => '00000',
        'phone' => '0772000002',
    ])->toArray();
    $address['address'] = ['University Road'];

    postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing' => [...$address, 'use_for_shipping' => false],
        'shipping' => $address,
    ])->assertOk();
    postJson(route('shop.checkout.onepage.shipping_methods.store'), ['shipping_method' => 'flatrate_flatrate'])->assertOk();
    postJson(route('shop.checkout.onepage.payment_methods.store'), ['payment' => ['method' => 'mobilemoney']])->assertOk();

    return app(CartRepository::class)->getModel()->where('customer_id', $customer->id)->latest('id')->firstOrFail();
}

beforeEach(function () {
    config(['classy.coursework_mode' => true, 'repository.cache.enabled' => false]);
    Http::preventStrayRequests();
});

it('completes a practice checkout once without a gateway or real transfer', function () {
    $cart = courseworkCart();
    $orders = app(OrderRepository::class);
    $before = $orders->getModel()->count();

    post(route('classy.mobilemoney.charge'), ['network' => 'MTN', 'phone' => '0772000002'])->assertRedirect();
    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();
    expect($attempt->provider)->toBe('coursework');
    get(route('classy.mobilemoney.status', $attempt))->assertOk()->assertSee('Practice payment')->assertSee('No money will be transferred');
    expect($orders->getModel()->count())->toBe($before);

    post(route('classy.mobilemoney.practice', $attempt), ['result' => 'success'])->assertRedirect();
    get(route('classy.mobilemoney.status', $attempt))->assertRedirect(route('shop.checkout.onepage.success'));
    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_SUCCESS);
    expect($orders->getModel()->count())->toBe($before + 1);

    post(route('classy.mobilemoney.practice', $attempt), ['result' => 'failed'])->assertRedirect();
    get(route('classy.mobilemoney.status', $attempt))->assertRedirect(route('shop.checkout.onepage.success'));
    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_SUCCESS);
    expect($orders->getModel()->count())->toBe($before + 1);
    Http::assertNothingSent();
});

it('keeps declined payments out of orders and retains the customer cart', function () {
    $cart = courseworkCart();
    $before = app(OrderRepository::class)->getModel()->count();
    post(route('classy.mobilemoney.charge'), ['network' => 'AIRTEL', 'phone' => '0752000002'])->assertRedirect();
    $attempt = PaymentAttempt::where('cart_id', $cart->id)->firstOrFail();

    post(route('classy.mobilemoney.practice', $attempt), ['result' => 'failed'])->assertRedirect();
    get(route('classy.mobilemoney.status', $attempt))->assertRedirect(route('shop.checkout.cart.index'));

    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_FAILED);
    expect(app(OrderRepository::class)->getModel()->count())->toBe($before);
    expect((bool) $cart->fresh()->is_active)->toBeTrue();
    Http::assertNothingSent();
});

it('rejects practice controls from other sessions and when project mode is disabled', function () {
    $cart = courseworkCart();
    $attempt = app(PaymentAttemptRepository::class)->create([
        'cart_id' => $cart->id,
        'tx_ref' => 'COURSE-OWNERSHIP', 'provider' => 'coursework', 'network' => 'MTN',
        'amount' => 25000, 'currency' => 'UGX', 'status' => PaymentAttempt::STATUS_PENDING,
    ]);
    post(route('classy.mobilemoney.practice', $attempt), ['result' => 'success'])->assertNotFound();
    session(['classy.mobilemoney.attempts' => [$attempt->public_id]]);
    config(['classy.coursework_mode' => false]);
    post(route('classy.mobilemoney.practice', $attempt), ['result' => 'success'])->assertNotFound();
    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PENDING);
});

it('captures notification previews without reporting a sent email', function () {
    config(['mail.default' => 'array']);
    Log::spy();
    Event::fake([MessageSent::class]);
    Mail::raw('Your order is being prepared.', fn ($message) => $message->to('student@example.test')->subject('Order update'));

    Event::assertNotDispatched(MessageSent::class);
    Log::shouldHaveReceived('info')->with('classy.coursework.notification', Mockery::on(fn ($data) => $data['subject'] === 'Order update' && $data['recipients'] === ['student@example.test']))->once();
});

it('prevents paid assistant requests and rejects external payment callbacks', function () {
    config(['classy.ai.api_key' => 'configured-but-unused']);
    expect(AiAssistant::enabled())->toBeFalse();
    expect(AiAssistant::chat([['role' => 'user', 'content' => 'shirts']]))->toBeNull();
    expect(Momo::provider('AIRTEL'))->toBeInstanceOf(CourseworkProvider::class);
    expect(Momo::manualEnabled())->toBeFalse();
    expect((new CourseworkProvider)->webhookIsValid(request()))->toBeFalse();
    Http::assertNothingSent();
});
