<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Models\Sales\Order as ClassyOrder;
use ClassyFashion\Support\Audit;
use ClassyFashion\Support\Notify;
use Webkul\Sales\Models\Order;

/**
 * Map core auto-transitions onto report (9.5/9.7) statuses.
 *
 * Core marks a fully invoiced + shipped order "completed". For Classy
 * Fashion that moment means the goods left the shop: Dispatched.
 * Delivery itself is recorded afterwards with a manual transition.
 */
class OrderStatusMapper
{
    public function mapCompletedToDispatched(Order $order): void
    {
        /*
         * Core marks a fully invoiced + shipped order "completed". For Classy
         * Fashion that moment means the goods left the shop: Dispatched.
         * Direct save() does not re-fire this event, so there is no loop.
         */
        if ($order->status === Order::STATUS_COMPLETED) {
            $order->status = ClassyOrder::STATUS_DISPATCHED;

            $order->save();

            Audit::log(
                $order,
                "Order #{$order->increment_id} auto-moved: completed to dispatched",
                ['from' => Order::STATUS_COMPLETED, 'to' => ClassyOrder::STATUS_DISPATCHED],
                auth('admin')->user(),
                'order.status'
            );

            Notify::orderStatus($order, ClassyOrder::STATUS_DISPATCHED);
        }
    }
}
