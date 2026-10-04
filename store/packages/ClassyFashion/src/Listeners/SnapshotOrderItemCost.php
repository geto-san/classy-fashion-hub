<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Support\Profit;
use Webkul\Sales\Models\OrderItem;

/**
 * Store the product's cost on the order item at the moment of sale
 * (report 9.4: records stay tied to the original transaction).
 */
class SnapshotOrderItemCost
{
    public function handle(OrderItem $orderItem): void
    {
        // Child rows of configurable items carry no price of their own.
        if ($orderItem->parent_id || $orderItem->cost_price !== null) {
            return;
        }

        $orderItem->cost_price = Profit::currentCost($orderItem);

        $orderItem->saveQuietly();
    }
}
