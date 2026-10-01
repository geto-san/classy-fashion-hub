<?php

namespace ClassyFashion\Http\Controllers\Admin;

use ClassyFashion\Models\Sales\Order as ClassyOrder;
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

        $this->orderRepository->updateOrderStatus($order, $request->input('status'));

        session()->flash('success', "Order #{$order->increment_id} is now {$order->fresh()->status_label}.");

        return redirect()->back();
    }
}
