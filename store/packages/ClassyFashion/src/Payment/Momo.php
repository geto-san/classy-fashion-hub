<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Models\Sales\Order;
use ClassyFashion\Repositories\PaymentAttemptRepository;
use ClassyFashion\Support\Audit;
use ClassyFashion\Support\CheckoutFingerprint;
use ClassyFashion\Support\Notify;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;

/**
 * Picks the mobile-money provider and fulfils human-confirmed payments.
 *
 * MTN MoMo API when its keys exist, otherwise the manual Till flow
 * (shop's own number, no signup). Else nothing (the method hides itself
 * at checkout).
 */
class Momo
{
    public static function provider(?string $network = null): ?MobileMoneyProvider
    {
        if (config('classy.coursework_mode')) {
            return new CourseworkProvider;
        }

        $forced = strtolower((string) config('classy.momo.provider', 'auto'));

        $mtn = new MtnMomoProvider;

        if ($forced === 'mtn') {
            return $mtn->isConfigured() ? $mtn : null;
        }

        if ($forced === 'manual') {
            return null;
        }

        if ($network !== 'AIRTEL' && $mtn->isConfigured()) {
            return $mtn;
        }

        return null;
    }

    public static function manualEnabled(): bool
    {
        return ! config('classy.coursework_mode') && filled(config('classy.momo.till_number'));
    }

    public static function tillNumber(): ?string
    {
        $till = config('classy.coursework_mode') ? null : config('classy.momo.till_number');

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

        $claimed = app(PaymentAttemptRepository::class)->claimManual($attempt);

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
        $cart = app(CartRepository::class)->find($attempt->cart_id);

        if (! $cart || $cart->is_active === false) {
            return self::unfulfilled($attempt, 'Cart no longer available after payment.', $causer);
        }

        try {
            Cart::setCart($cart);
            if (! Cart::validateItems()) {
                return self::unfulfilled($attempt, 'Products are no longer available after payment.', $causer);
            }

            Cart::collectTotals();

            $cart->refresh();

            if (round((float) $cart->grand_total, 2) !== round((float) $attempt->amount, 2)
                || $cart->cart_currency_code !== $attempt->currency) {
                return self::unfulfilled($attempt, 'Checkout total or currency changed after payment.', $causer);
            }

            if (! $attempt->checkout_fingerprint || ! hash_equals($attempt->checkout_fingerprint, CheckoutFingerprint::forCart($cart))) {
                return self::unfulfilled($attempt, 'Checkout contents changed or predate payment snapshots; staff reconciliation required.', $causer);
            }

            $claimed = app(PaymentAttemptRepository::class)->claimCart($cart->id);

            if ($claimed !== 1) {
                return self::unfulfilled($attempt, 'Another payment already claimed this cart; staff reconciliation required.', $causer);
            }

            $repository = app(OrderRepository::class);

            $order = $repository->create((new OrderResource($cart))->jsonSerialize());

            $repository->updateOrderStatus($order, Order::STATUS_PAID);
        } catch (\Throwable $e) {
            report($e);

            return self::unfulfilled($attempt, 'Order creation failed after payment.', $causer);
        }

        $channel = $via ?? ($attempt->provider === 'coursework'
            ? 'coursework payment simulation (no money transferred)'
            : "mobile money ({$attempt->network}, {$attempt->tx_ref})");

        Audit::log(
            $order->fresh(),
            "Order #{$order->increment_id} paid via {$channel}",
            ['tx_ref' => $attempt->tx_ref, 'network' => $attempt->network, 'to' => 'paid'],
            $causer,
            'order.payment'
        );

        $attempt->update(['status' => PaymentAttempt::STATUS_SUCCESS, 'order_id' => $order->id]);

        Cart::deActivateCart();

        if (session()->isStarted()) {
            session()->flash('order_id', $order->id);
        }

        Notify::orderStatus($order->fresh(), Order::STATUS_PAID);

        return PaymentAttempt::STATUS_SUCCESS;
    }

    public static function unfulfilled(PaymentAttempt $attempt, string $reason, $causer = null): string
    {
        $attempt->update([
            'status' => PaymentAttempt::STATUS_PAID_UNFULFILLED,
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
