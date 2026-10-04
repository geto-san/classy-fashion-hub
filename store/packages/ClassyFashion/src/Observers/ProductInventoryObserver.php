<?php

namespace ClassyFashion\Observers;

use ClassyFashion\Support\Audit;
use ClassyFashion\Support\Notify;
use Webkul\Product\Models\ProductInventory;

/**
 * Audit trail for stock changes (report 9.3/10.2).
 *
 * Fires on every inventory write. Only staff (admin-guard) actions are
 * written to the audit log, so customer checkouts do not flood it (those
 * stay traceable via orders). The low-stock e-mail to the owner is sent
 * whoever caused the drop, including a customer's checkout (report 9.8).
 */
class ProductInventoryObserver
{
    public function updating(ProductInventory $inventory): void
    {
        if (! $inventory->isDirty('qty')) {
            return;
        }

        $admin = auth('admin')->user();

        $product = $inventory->product;

        if ($admin) {
            $this->logStockChange($inventory, $product, $admin);
        }

        $this->handleThresholdCrossing($inventory, $admin, (int) $inventory->getOriginal('qty'), (int) $inventory->qty);
    }

    protected function logStockChange(ProductInventory $inventory, $product, $admin): void
    {
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

    /**
     * When quantity drops to or below the configured out-of-stock threshold
     * (report 9.8): e-mail the owner, and log it when staff caused the drop.
     * The admin dashboard threshold widget surfaces the same products.
     */
    protected function handleThresholdCrossing(ProductInventory $inventory, $admin, int $oldQty, int $newQty): void
    {
        $threshold = (int) core()->getConfigData('catalog.inventory.stock_options.out_of_stock_threshold');

        if ($oldQty > $threshold && $newQty <= $threshold) {
            Notify::lowStock($inventory->product?->sku, $newQty, $threshold);

            if (! $admin) {
                return;
            }

            Audit::log(
                $inventory->product ?? $inventory,
                "Low stock for {$inventory->product?->sku}: {$newQty} left (threshold {$threshold})",
                [
                    'sku'        => $inventory->product?->sku,
                    'product_id' => $inventory->product_id,
                    'qty'        => $newQty,
                    'threshold'  => $threshold,
                ],
                $admin,
                'stock.low'
            );
        }
    }
}
