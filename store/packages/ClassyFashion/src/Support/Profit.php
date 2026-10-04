<?php

namespace ClassyFashion\Support;

use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderItem;

/**
 * Profit per sale (report 9.4).
 *
 * Cost is the figure stored on the order item when it was sold
 * (order_items.cost_price), so later supplier-price edits never rewrite
 * past profit. Only items sold before that column existed and could not be
 * backfilled fall back to the product's current cost attribute (variant
 * cost falls back to the parent product). Single-currency store: price
 * equals base price.
 */
class Profit
{
    public static function itemCost(OrderItem $item): float
    {
        if ($item->cost_price !== null) {
            return (float) $item->cost_price;
        }

        return self::currentCost($item);
    }

    /**
     * What the product costs today (used to take the snapshot).
     */
    public static function currentCost(OrderItem $item): float
    {
        $product = $item->product;

        $cost = $product?->cost;

        if (
            ($cost === null || (float) $cost <= 0)
            && $product?->parent_id
        ) {
            $cost = $product->parent?->cost;
        }

        return (float) ($cost ?? 0);
    }

    public static function itemProfit(OrderItem $item): float
    {
        return ((float) $item->base_price - self::itemCost($item)) * (float) $item->qty_ordered;
    }

    public static function orderProfit(Order $order): float
    {
        return $order->items->sum(fn (OrderItem $item) => self::itemProfit($item));
    }
}
