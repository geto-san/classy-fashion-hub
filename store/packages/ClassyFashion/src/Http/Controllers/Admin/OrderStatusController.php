<?php

namespace ClassyFashion\Http\Controllers\Admin;

use ClassyFashion\Models\Sales\Order as ClassyOrder;
use ClassyFashion\Support\Audit;
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

        if (! $order->canTransitionTo($request->input('status'))) {
            return redirect()->back()->withErrors([
                'status' => "Cannot move order #{$order->increment_id} from {$order->status} to {$request->input('status')}.",
            ], 'order_status');
        }

        $from = $order->status;

        $this->orderRepository->updateOrderStatus($order, $request->input('status'));

        Audit::log(
            $order->fresh(),
            "Order #{$order->increment_id} status: {$from} to {$order->fresh()->status}",
            ['from' => $from, 'to' => $order->fresh()->status],
            auth('admin')->user(),
            'order.status'
        );

        session()->flash('success', "Order #{$order->increment_id} is now {$order->fresh()->status_label}.");

        return redirect()->back();
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
