<?php

namespace ClassyFashion\Support;

use Webkul\Checkout\Models\Cart;

/**
 * Bind payment approval to the products and quantities submitted at checkout.
 */
class CheckoutFingerprint
{
    public static function forCart(Cart $cart): string
    {
        $items = $cart->items()->orderBy('id')->get()->map(fn ($item) => [
            'product_id' => $item->product_id,
            'sku' => $item->sku,
            'quantity' => (float) $item->quantity,
            'price' => (float) $item->price,
            'additional' => $item->additional,
        ])->all();

        return hash('sha256', json_encode([
            'items' => $items,
            'total' => (float) $cart->grand_total,
            'currency' => $cart->cart_currency_code,
            'shipping_method' => $cart->shipping_method,
        ], JSON_THROW_ON_ERROR));
    }
}
