<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Support\Audit;

/**
 * Picks the mobile-money provider and fulfils human-confirmed payments.
 *
 * Order: explicit MOMO_PROVIDER override, then MTN keys, then Flutterwave
 * keys, then the manual Till flow (shop's own number, no signup), else
 * nothing (the method hides itself at checkout).
 */
class Momo
{
    public static function provider(?string $network = null): ?MobileMoneyProvider
    {
        $forced = strtolower((string) config('classy.momo.provider', 'auto'));

        $mtn = new MtnMomoProvider;
        $flutterwave = new FlutterwaveProvider;
        $pesapal = new PesapalProvider;

        if ($forced === 'mtn') {
            return $mtn->isConfigured() ? $mtn : null;
        }

        if ($forced === 'flutterwave') {
            return $flutterwave->isConfigured() ? $flutterwave : null;
        }

        if ($forced === 'pesapal') {
            return $pesapal->isConfigured() ? $pesapal : null;
        }

        if ($forced === 'manual') {
            return null;
        }

        // Auto: Pesapal first (own hosted page, both networks), then MTN for
        // MTN numbers, then Flutterwave, then the manual Till flow.
        if ($pesapal->isConfigured()) {
            return $pesapal;
        }

        // Auto: MTN only speaks MTN; Flutterwave covers both networks.
        if ($network === 'MTN' && $mtn->isConfigured()) {
            return $mtn;
        }

        if ($flutterwave->isConfigured()) {
            return $flutterwave;
        }

        if ($network !== 'AIRTEL' && $mtn->isConfigured()) {
            return $mtn;
        }

        return null;
    }

    public static function manualEnabled(): bool
    {
        return filled(config('classy.momo.till_number'));
    }

    public static function tillNumber(): ?string
    {
        $till = config('classy.momo.till_number');

        return filled($till) ? (string) $till : null;
    }

    /**
     * Staff confirms a customer-claimed Till payment: same atomic claim as
     * the gateway path, then the normal paid-order creation. The causer is
     * the staff member, so accountability is kept (report 10.2).
     *
     * @return string success|paid_unfulfilled|failed
     */
    public static function fulfillManual(PaymentAttempt $attempt, $admin): string
    {
        $attempt->refresh();

        if ($attempt->status !== PaymentAttempt::STATUS_PENDING && $attempt->status !== PaymentAttempt::STATUS_EXPIRED) {
            return $attempt->status;
        }

        if (! filled($attempt->customer_claim)) {
            return PaymentAttempt::STATUS_FAILED;
        }

        $claimed = PaymentAttempt::query()
            ->whereKey($attempt->getKey())
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_EXPIRED])
            ->update([
                'status'      => PaymentAttempt::STATUS_FINALIZING,
                'verified_at' => now(),
            ]);

        if ($claimed === 0) {
            return $attempt->fresh()->status;
        }

        return self::createPaidOrder($attempt->refresh(), $admin, 'Till '.$attempt->customer_claim);
    }

    /**
     * Create the paid order from a claimed attempt. Shared by the gateway
     * path (system causer) and the manual path (staff causer).
     */
    public static function createPaidOrder(PaymentAttempt $attempt, $causer = null, ?string $via = null): string
    {
        $cart = \Webkul\Checkout\Models\Cart::find($attempt->cart_id);

        if (! $cart || $cart->is_active === false) {
            return self::unfulfilled($attempt, 'Cart no longer available after payment.', $causer);
        }

        try {
            \Webkul\Checkout\Facades\Cart::setCart($cart);
            \Webkul\Checkout\Facades\Cart::collectTotals();

            $repository = app(\Webkul\Sales\Repositories\OrderRepository::class);

            $order = $repository->create((new \Webkul\Sales\Transformers\OrderResource($cart))->jsonSerialize());

            $repository->updateOrderStatus($order, \ClassyFashion\Models\Sales\Order::STATUS_PAID);
        } catch (\Throwable $e) {
            report($e);

            return self::unfulfilled($attempt, 'Order creation failed after payment.', $causer);
        }

        $channel = $via ?? "mobile money ({$attempt->network}, {$attempt->tx_ref})";

        Audit::log(
            $order->fresh(),
            "Order #{$order->increment_id} paid via {$channel}",
            ['tx_ref' => $attempt->tx_ref, 'network' => $attempt->network, 'to' => 'paid'],
            $causer,
            'order.payment'
        );

        $attempt->update(['status' => PaymentAttempt::STATUS_SUCCESS, 'order_id' => $order->id]);

        \Webkul\Checkout\Facades\Cart::deActivateCart();

        if (session()->isStarted()) {
            session()->flash('order_id', $order->id);
        }

        \ClassyFashion\Support\Notify::orderStatus($order->fresh(), \ClassyFashion\Models\Sales\Order::STATUS_PAID);

        return PaymentAttempt::STATUS_SUCCESS;
    }

    public static function unfulfilled(PaymentAttempt $attempt, string $reason, $causer = null): string
    {
        $attempt->update([
            'status'         => PaymentAttempt::STATUS_PAID_UNFULFILLED,
            'failure_reason' => $reason,
        ]);

        Audit::log(
            $attempt,
            "Payment {$attempt->tx_ref} verified but no order was created: {$reason}",
            ['tx_ref' => $attempt->tx_ref, 'amount' => $attempt->amount, 'network' => $attempt->network],
            $causer,
            'order.payment_unfulfilled'
        );

        return PaymentAttempt::STATUS_PAID_UNFULFILLED;
    }
}
