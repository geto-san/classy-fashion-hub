<?php

namespace ClassyFashion\Http\Controllers\Admin;

use ClassyFashion\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Mobile-money payment attempts (report 9.6): every attempt with its gateway
 * reference, and the ones that need a person (paid but no order).
 */
class PaymentAttemptController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(bouncer()->hasPermission('sales.payments'), 401);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $query = PaymentAttempt::query()->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $attention = PaymentAttempt::query()
            ->where(function ($q) {
                $q->where('status', PaymentAttempt::STATUS_PAID_UNFULFILLED)
                    ->orWhere(function ($q) {
                        $q->where('status', PaymentAttempt::STATUS_FINALIZING)
                            ->where('updated_at', '<', now()->subMinutes(5));
                    });
            })
            ->count();

        return view('classy-fashion::admin.payments.index', [
            'attempts'  => $query->paginate(50)->withQueryString(),
            'attention' => $attention,
            'statuses'  => [
                PaymentAttempt::STATUS_PENDING,
                PaymentAttempt::STATUS_FINALIZING,
                PaymentAttempt::STATUS_SUCCESS,
                PaymentAttempt::STATUS_FAILED,
                PaymentAttempt::STATUS_EXPIRED,
                PaymentAttempt::STATUS_PAID_UNFULFILLED,
            ],
            'filters'   => $filters,
        ]);
    }
}
