<?php

namespace ClassyFashion\Observers;

use ClassyFashion\Support\Audit;
use Webkul\Product\Models\ProductInventory;

/**
 * Audit trail for stock changes (report 9.3/10.2).
 *
 * Fires on every inventory write (admin product saves, worker stock
 * edits) but only records staff (admin-guard) actions, so customer
 * checkouts do not flood the log; those remain traceable via orders.
 */
class ProductInventoryObserver
{
    public function updating(ProductInventory $inventory): void
    {
        if (! $inventory->isDirty('qty')) {
            return;
        }

        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        $product = $inventory->product;

        Audit::log(
            $product ?? $inventory,
            "Stock updated for {$product?->sku}: {$inventory->getOriginal('qty')} to {$inventory->qty}",
            [
                'sku'          => $product?->sku,
                'product_id'   => $inventory->product_id,
                'source_id'    => $inventory->inventory_source_id,
                'old_qty'      => $inventory->getOriginal('qty'),
                'new_qty'      => $inventory->qty,
            ],
            $admin,
            'stock.updated'
        );
    }
}
