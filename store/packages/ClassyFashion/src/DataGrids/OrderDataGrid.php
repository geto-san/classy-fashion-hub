<?php

namespace ClassyFashion\DataGrids;

use ClassyFashion\Models\Sales\Order as ClassyOrder;
use Webkul\Admin\DataGrids\Sales\OrderDataGrid as BaseOrderDataGrid;

/**
 * Admin order list that knows the report statuses (9.5/9.7).
 *
 * The core grid only renders and filters core statuses, so an order that is
 * Confirmed, Paid, Dispatched or Delivered showed a blank status cell and
 * could not be filtered. Bound over the core grid in the service provider;
 * no core file is touched. Legacy statuses the shop does not use
 * (completed, closed, fraud) are left out of the filter.
 */
class OrderDataGrid extends BaseOrderDataGrid
{
    /**
     * status => [label, css class]
     */
    protected const STATUSES = [
        ClassyOrder::STATUS_PENDING         => ['Pending', 'label-pending'],
        ClassyOrder::STATUS_PENDING_PAYMENT => ['Pending Payment', 'label-pending'],
        ClassyOrder::STATUS_CONFIRMED       => ['Confirmed', 'label-pending'],
        ClassyOrder::STATUS_PAID            => ['Paid', 'label-active'],
        ClassyOrder::STATUS_PROCESSING      => ['Processing', 'label-processing'],
        ClassyOrder::STATUS_DISPATCHED      => ['Dispatched', 'label-processing'],
        ClassyOrder::STATUS_DELIVERED       => ['Delivered', 'label-active'],
        ClassyOrder::STATUS_CANCELED        => ['Canceled', 'label-canceled'],
    ];

    /**
     * Legacy core statuses still rendered (never offered as filters).
     */
    protected const LEGACY = [
        ClassyOrder::STATUS_COMPLETED => ['Completed', 'label-active'],
        ClassyOrder::STATUS_CLOSED    => ['Closed', 'label-closed'],
        ClassyOrder::STATUS_FRAUD     => ['Fraud', 'label-canceled'],
    ];

    public function prepareColumns()
    {
        parent::prepareColumns();

        foreach ($this->columns as $column) {
            if ($column->getIndex() !== 'status') {
                continue;
            }

            $column->setFilterableOptions(array_map(
                fn (string $status, array $meta) => ['label' => $meta[0], 'value' => $status],
                array_keys(self::STATUSES),
                self::STATUSES
            ));

            $column->setClosure(function ($row) {
                $meta = self::STATUSES[$row->status] ?? self::LEGACY[$row->status] ?? [ucfirst((string) $row->status), 'label-pending'];

                return '<p class="'.e($meta[1]).'">'.e($meta[0]).'</p>';
            });
        }
    }
}
