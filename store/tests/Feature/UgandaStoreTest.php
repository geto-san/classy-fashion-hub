<?php

use Webkul\Customer\Models\Customer;
use Webkul\Customer\Models\CustomerAddress;
use Webkul\Product\Models\Product;

use function Pest\Laravel\postJson;

it('fits the Ugandan context: UGX only, local payments, no postcode', function () {
    putenv('FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-xxx');
    putenv('FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-xxx');

    $customer = Customer::where('email', 'customer@classy.local')->firstOrFail();

    $this->actingAs($customer);

    expect(core()->getCurrentCurrency()->code)->toBe('UGX')
        ->and(core()->getConfigData('customer.address.requirements.postcode'))->toBeFalsy()
        ->and(core()->getConfigData('customer.address.requirements.state'))->toBeFalsy();

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

    // Ugandan address: no state, no postcode, +256 phone.
    $address = [
        'first_name' => 'Kampala',
        'last_name'  => 'Shopper',
        'email'      => $customer->email,
        'address'    => ['Plot 12 Kampala Road'],
        'city'       => 'Kampala',
        'country'    => 'UG',
        'phone'      => '+256772000002',
    ];

    $addrResponse = postJson(route('shop.checkout.onepage.addresses.store'), [
        'billing'  => [...$address, 'use_for_shipping' => false],
        'shipping' => [...$address],
    ])->assertOk();

    $addrResponse->assertJsonPath('data.shippingMethods.flatrate.rates.0.method', 'flatrate_flatrate');

    expect((float) $addrResponse->json('data.shippingMethods.flatrate.rates.0.price'))->toBe(5000.0);

    // Only Ugandan payment modes + UGX 5,000 flat delivery.
    $response = postJson(route('shop.checkout.onepage.shipping_methods.store'), [
        'shipping_method' => 'flatrate_flatrate',
    ])->assertOk();

    $methods = collect($response->json('payment_methods'))->pluck('method')->all();

    expect($methods)->toContain('cashondelivery', 'mobilemoney')
        ->and($methods)->not->toContain('stripe', 'paypal_standard', 'razorpay', 'payu', 'phonepe', 'payglocal');
});
