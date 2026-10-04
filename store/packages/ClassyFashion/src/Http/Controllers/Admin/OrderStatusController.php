<?php

namespace ClassyFashion\Http\Controllers\Admin;

use ClassyFashion\Models\ManualPayment;
use ClassyFashion\Models\Sales\Order as ClassyOrder;
use ClassyFashion\Support\Audit;
use ClassyFashion\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Sales\Repositories\OrderRepository;

/**
 * Manual order status transitions (report 9.5/9.7).
 *
 * Only transitions in ClassyOrder::TRANSITIONS are allowed and only for
 * staff holding the orders permission (server-side; Bouncer middleware
 * already guarantees an authenticated admin).
 */
class OrderStatusController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'string'],
        ]);

        /** @var ClassyOrder $order */
        $order = $this->orderRepository->findOrFail($id);

        abort_unless(bouncer()->hasPermission('sales.orders.status'), 401);

        if (! $order instanceof ClassyOrder) {
            $order = ClassyOrder::findOrFail($id);
        }

        $target = (string) $request->input('status');

        if (! $order->canTransitionTo($target)) {
            return redirect()->back()->withErrors([
                'status' => "Cannot move order #{$order->increment_id} from {$order->status} to {$target}.",
            ], 'order_status');
        }

        $from = $order->status;

        $admin = auth('admin')->user();

        if ($target === ClassyOrder::STATUS_CANCELED) {
            // Core cancel puts the stock back; a bare status change did not.
            if (! $this->orderRepository->cancel($order, true)) {
                return redirect()->back()->withErrors([
                    'status' => "Order #{$order->increment_id} cannot be canceled here (items already invoiced or shipped).",
                ], 'order_status');
            }
        } else {
            $this->orderRepository->updateOrderStatus($order, $target);
        }

        $order = $order->fresh();

        $this->recordPayment($order, $target, $admin);

        Notify::orderStatus($order, $target);

        $refundDue = $target === ClassyOrder::STATUS_CANCELED
            && $from === ClassyOrder::STATUS_PAID
            && $order->usesMobileMoney();

        Audit::log(
            $order,
            "Order #{$order->increment_id} status: {$from} to {$order->status}".($refundDue ? ' (refund due)' : ''),
            ['from' => $from, 'to' => $order->status, 'refund_due' => $refundDue],
            $admin,
            'order.status'
        );

        if ($refundDue) {
            session()->flash('warning', "Order #{$order->increment_id} was paid by mobile money: refund ".core()->formatBasePrice($order->base_grand_total).' to the customer from the Flutterwave dashboard.');
        }

        session()->flash('success', "Order #{$order->increment_id} is now {$order->status_label}.");

        return redirect()->back();
    }

    /**
     * 'Paid' by hand and cash collected on delivery are written down with who
     * took the money, so no payment is ever unexplained (report 9.5/9.6).
     */
    protected function recordPayment(ClassyOrder $order, string $status, $admin): void
    {
        if ($status === ClassyOrder::STATUS_PAID) {
            ManualPayment::record($order, $order->payment?->method ?: 'manual', $admin);
        }

        if ($status === ClassyOrder::STATUS_DELIVERED && $order->isCashOnDelivery()) {
            ManualPayment::record($order, 'cashondelivery', $admin, 'Cash collected on delivery');
        }
    }

    /**
     * Assign a delivery partner / rider to an order (report 9.7).
     */
    public function partner(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'delivery_partner' => ['nullable', 'string', 'max:100'],
        ]);

        $order = $this->orderRepository->findOrFail($id);

        abort_unless(bouncer()->hasPermission('sales.orders.status'), 401);

        $from = $order->delivery_partner;

        $order->update(['delivery_partner' => $request->input('delivery_partner') ?: null]);

        Audit::log(
            $order->fresh(),
            "Order #{$order->increment_id} delivery partner: ".($from ?: 'none').' to '.($order->fresh()->delivery_partner ?: 'none'),
            ['from' => $from, 'to' => $order->fresh()->delivery_partner],
            auth('admin')->user(),
            'order.delivery'
        );

        session()->flash('success', __('classy-fashion::app.orders.partner_saved'));

        return redirect()->back();
    }
}
