<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\Cart as CartModel;
use Webkul\Checkout\Models\CartAddress;
use Webkul\Checkout\Models\CartItem;
use Webkul\Checkout\Models\CartPayment;
use Webkul\Checkout\Models\CartShippingRate;
use Webkul\Customer\Models\Customer;
use Webkul\Customer\Models\CustomerAddress;
use Webkul\Product\Models\Product;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;

/**
 * Demo orders for screenshots and report verification (not test data).
 *
 * Builds real carts for customer@classy.local through the same pipeline
 * the checkout uses, then places orders: one pending, one paid, one
 * delivered. Safe to re-run (skips when demo orders already exist).
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (\Webkul\Sales\Models\Order::where('customer_email', 'customer@classy.local')->count() >= 3) {
            $this->command->info('Demo orders already present.');

            return;
        }

        $customer = Customer::where('email', 'customer@classy.local')->firstOrFail();

        $products = Product::where('type', 'configurable')->with('variants')->take(3)->get();

        $statuses = ['pending', 'paid', 'delivered'];

        foreach ($products as $i => $product) {
            $variant = $product->variants->first();

            $order = $this->placeOrder($customer, $product, $variant);

            $this->transition($order, $statuses[$i]);

            $this->command->line("  + order #{$order->increment_id} ({$product->name}) -> {$statuses[$i]}");
        }
    }

    protected function placeOrder(Customer $customer, Product $product, Product $variant): \Webkul\Sales\Models\Order
    {
        $price = (float) ($variant->price ?: $product->price);

        $cart = CartModel::factory()->create([
            'customer_id'         => $customer->id,
            'customer_first_name' => $customer->first_name,
            'customer_last_name'  => $customer->last_name,
            'customer_email'      => $customer->email,
            'is_guest'            => 0,
            'shipping_method'     => 'flatrate_flatrate',
        ]);

        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $variant->id,
            'sku'        => $variant->sku,
            'quantity'   => 1,
            'name'       => $product->name,
            'price'      => $price,
            'base_price' => $price,
            'total'      => $price,
            'base_total' => $price,
            'weight'     => 1,
            'type'       => 'simple',
            'additional' => ['product_id' => $variant->id, 'quantity' => 1],
        ]);

        $source = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

        $address = [
            'company_name' => $source->company_name,
            'first_name'   => $source->first_name,
            'last_name'    => $source->last_name,
            'email'        => $source->email,
            'address'      => 'Plot 12 Kampala Road',
            'country'      => $source->country,
            'state'        => $source->state,
            'city'         => $source->city,
            'postcode'     => $source->postcode,
            'phone'        => '+256772000002',
        ];

        foreach ([CartAddress::ADDRESS_TYPE_BILLING, CartAddress::ADDRESS_TYPE_SHIPPING] as $type) {
            CartAddress::factory()->create(array_merge($address, [
                'cart_id'      => $cart->id,
                'address_type' => $type,
            ]));
        }

        CartPayment::factory()->create(['cart_id' => $cart->id, 'method' => 'cashondelivery']);

        $shippingAddress = $cart->shipping_address()->first();

        CartShippingRate::factory()->create([
            'carrier'         => 'flatrate',
            'method'          => 'flatrate_flatrate',
            'cart_address_id' => $shippingAddress->id,
            'cart_id'         => $cart->id,
        ]);

        Cart::setCart($cart->fresh());
        Cart::collectTotals();

        $data = (new OrderResource(Cart::getCart()))->jsonSerialize();

        /*
         * db:seed runs unguarded, which would push nested address/payment/
         * item payloads into the orders INSERT. Guard like the HTTP path.
         */
        \Illuminate\Database\Eloquent\Model::reguard();

        try {
            $order = app(OrderRepository::class)->create($data);
        } finally {
            \Illuminate\Database\Eloquent\Model::unguard();
        }

        Cart::deActivateCart();

        return $order;
    }

    protected function transition(\Webkul\Sales\Models\Order $order, string $target): void
    {
        $repo = app(OrderRepository::class);

        $chain = [
            'pending'   => [],
            'paid'      => ['confirmed', 'paid'],
            'delivered' => ['confirmed', 'paid', 'processing', 'dispatched', 'delivered'],
        ];

        foreach ($chain[$target] as $status) {
            $repo->updateOrderStatus($order->fresh(), $status);
        }
    }
}
