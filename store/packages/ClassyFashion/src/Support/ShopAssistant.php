<?php

namespace ClassyFashion\Support;

use Webkul\Customer\Models\Customer as CustomerModel;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;

/**
 * Shop assistant brain (report 5.1, conversational search + support).
 *
 * Rule-based first: delivery, payments, sizing, order tracking and a
 * keyword product finder that understands colours, sizes, categories
 * and UGX prices ("red dresses under 100000"). An LLM pass improves
 * open questions only when keys exist; the shop works fully without.
 * Voice follows Brand Guidelines v1.0: short, plain, confirm each step.
 */
class ShopAssistant
{
    public static function answer(string $message, ?CustomerModel $customer = null): array
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return self::reply("Hello! Ask me about products, prices, delivery, payments or your order.");
        }

        if (self::wants($text, ['track', 'where is my order', 'order status', 'my order', 'delivery status', 'has it arrived'])) {
            return self::orderStatus($customer);
        }

        if (self::wants($text, ['deliver', 'shipping', 'ship ', 'how long', 'fee', 'charge', 'kampala', 'upcountry', 'pickup', 'pick up'])) {
            return self::reply(
                "Delivery is a flat USh 5,000 anywhere in Uganda. Kampala arrives within 24 hours;".
                " upcountry takes 2–4 days. Add gate or landmark notes in the delivery instructions at checkout,"
                ." and the rider will call on arrival. The price you see is the price you pay."
            );
        }

        if (self::wants($text, ['pay', 'payment', 'mtn', 'airtel', 'mobile money', 'cash', 'flutterwave', 'deposit'])) {
            return self::reply(
                "Pay with MTN Mobile Money or Airtel Money — approve the prompt on your phone and your order".
                " turns Paid only after the network confirms. Cash on delivery is available too."
            );
        }

        if (self::wants($text, ['size', 'fit', 'measurement', 'small', 'large', 'xl'])) {
            return self::reply(
                "Tops run S to XL; shoes are listed by EU size on each product. Size guide: if you are".
                " between sizes, go up. Tell me a product and I will list its in-stock sizes."
            );
        }

        if (self::wants($text, ['return', 'refund', 'exchange', 'faulty', 'wrong size'])) {
            return self::reply(
                "Unworn items with tags can be returned within 7 days for exchange or mobile-money refund.".
                " Reply with your order number and we shall arrange a Kampala pickup."
            );
        }

        if (self::wants($text, ['price', 'cost', 'how much', 'cheap', 'expensive', 'catalog', 'catalogue', 'what do you sell', 'products'])) {
            return self::catalog($text);
        }

        if (self::wants($text, ['hello', 'hi', 'hey', 'morning', 'afternoon', 'evening']) && mb_strlen($text) < 20) {
            return self::reply("Hello, and welcome to Classy Fashion Hub! Ask me for a product, a price, delivery or payment help.");
        }

        if (self::wants($text, ['thank', 'thanks', 'bye', 'bye-bye'])) {
            return self::reply("Thank you for shopping with Classy Fashion Hub.");
        }

        $found = self::findProducts($text);

        if (! empty($found['products'])) {
            return $found;
        }

        $llm = AiAssistant::chat([
            ['role' => 'system', 'content' => self::systemPrompt()],
            ['role' => 'user', 'content' => mb_substr($message, 0, 500)],
        ]);

        if ($llm) {
            return self::reply($llm);
        }

        return self::reply(
            "I can help with products, prices, sizes, delivery, payments, returns and order tracking.".
            " Try “red dresses under 100000” or “where is my order?”."
        );
    }

    protected static function wants(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected static function reply(string $text, array $products = []): array
    {
        return ['reply' => $text, 'products' => $products];
    }

    protected static function orderStatus(?CustomerModel $customer): array
    {
        if (! $customer) {
            return self::reply("Log in and ask again — I will fetch your latest order and its live status.");
        }

        $order = Order::where('customer_id', $customer->id)->latest('id')->first();

        if (! $order) {
            return self::reply("You have no orders yet. Tell me what you are looking for and I will find it.");
        }

        $label = ucfirst($order->status);

        return self::reply(
            "Your latest order #{$order->increment_id} ({$order->items->count()} item(s), ".
            core()->formatPrice((float) $order->grand_total, $order->order_currency_code).
            ") is: {$label}. I will confirm every step until it is Delivered."
        );
    }

    protected static function catalog(string $text): array
    {
        $found = self::findProducts($text);

        if (! empty($found['products'])) {
            return $found;
        }

        $count = Product::whereNull('parent_id')->count();

        return self::reply(
            "We stock shirts, jackets, dresses and shoes for men and women — {$count} styles with fixed USh prices.".
            " Tell me a colour, size or max price and I will narrow it down."
        );
    }

    /**
     * Keyword product finder: colours, sizes, categories, UGX ceilings.
     */
    public static function findProducts(string $text, int $limit = 3): array
    {
        $query = Product::query()
            ->whereNull('products.parent_id')
            ->join('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.locale', 'en')
                    ->where('product_flat.channel', 'default');
            })
            ->where('product_flat.status', 1)
            ->select('products.*');

        $matched = false;

        $colors = \DB::table('attribute_options')
            ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')
            ->where('attributes.code', 'color')
            ->pluck('attribute_options.admin_name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();

        foreach ($colors as $color) {
            if ($color !== '' && str_contains($text, $color)) {
                // Colours live on variants: match parents having such a variant.
                $query->whereExists(function ($q) use ($color) {
                    $q->selectRaw(1)->from('products as variants')
                        ->join('product_attribute_values as pav', 'pav.product_id', '=', 'variants.id')
                        ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
                        ->join('attribute_options as ao', 'ao.id', '=', 'pav.integer_value')
                        ->whereColumn('variants.parent_id', 'products.id')
                        ->where('a.code', 'color')
                        ->whereRaw('LOWER(ao.admin_name) = ?', [$color]);
                });

                $matched = true;

                break;
            }
        }

        $categories = ['mens' => 2, 'womens' => 4, 'women' => 4, 'men' => 2, 'footwear' => 8, 'shoes' => 8, 'casual' => 6];

        foreach ($categories as $word => $categoryId) {
            if (str_contains($text, $word)) {
                $query->join('product_categories', 'product_categories.product_id', '=', 'products.id')
                    ->where('product_categories.category_id', $categoryId);

                $matched = true;

                break;
            }
        }

        foreach (['shirt', 'jacket', 'dress', 'sneaker', 'loafer', 'belt', 'scarf', 'bag', 'cap', 'beanie', 'sock', 'gomesi', 'kitenge', 'ankara'] as $word) {
            if (str_contains($text, $word)) {
                $query->where('product_flat.name', 'like', '%'.$word.'%');

                $matched = true;

                break;
            }
        }

        if (preg_match('/(?:under|below|max|less than)\s*([\d\s,]+)k?\b/', $text, $m)) {
            $ceiling = (int) preg_replace('/\D/', '', $m[1]);

            if (str_ends_with(trim($m[1]), 'k')) {
                $ceiling *= 1000;
            }

            if ($ceiling > 0) {
                $query->where('product_flat.price', '<=', $ceiling);

                $matched = true;
            }
        }

        if (! $matched) {
            return self::reply('');
        }

        $got = $query->orderBy('product_flat.price')->limit($limit)->get();
        $items = $got
            ->filter(fn ($p) => $p->haveSufficientQuantity(1))
            ->map(fn ($p) => [
                'name'  => $p->name,
                'price' => core()->formatPrice((float) $p->price),
                'url'   => url($p->url_key),
            ])
            ->values()
            ->all();

        if (empty($items)) {
            return self::reply("Nothing matches that combination right now — try a different colour, size or a higher ceiling. The price you see is the price you pay.");
        }

        $names = implode(', ', array_column($items, 'name'));
        $ret = self::reply("Here is what matches: {$names}. Fixed prices, in stock.", $items);
        return $ret;
    }

    protected static function systemPrompt(): string
    {
        return 'You are the Classy Fashion Hub shop assistant (Kampala, Uganda). Short plain sentences.'.
            ' Fixed USh prices, MTN/Airtel mobile money or cash on delivery, USh 5,000 flat delivery'.
            ' (Kampala 24h, upcountry 2-4 days), 7-day returns. Never invent products or prices.';
    }
}
