<?php

namespace ClassyFashion\Http\Controllers\Shop;

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Support\Audit;
use ClassyFashion\Support\Flutterwave;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

/**
 * Flutterwave mobile-money flow (report 9.6, sandbox-capable).
 *
 * The order is created ONLY after server-side verification (verify API
 * call on our status check, or signature-checked webhook). Returning
 * from any payment page proves nothing and creates no order.
 */
class MobileMoneyController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    /**
     * Entry from checkout: pick network, then charge.
     */
    public function redirect()
    {
        $cart = Cart::getCart();

        if (! $cart || $cart->payment?->method !== 'mobilemoney') {
            return redirect()->route('shop.checkout.cart.index');
        }

        if ($cart->cart_currency_code !== 'UGX') {
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', __('classy-fashion::app.mobilemoney.ugx_only'));
        }

        return view('classy-fashion::shop.mobilemoney.redirect', [
            'cart'     => $cart,
            'networks' => ['MTN' => 'MTN Mobile Money', 'AIRTEL' => 'Airtel Money'],
        ]);
    }

    /**
     * Create the gateway charge for the chosen network.
     */
    public function charge(Request $request)
    {
        $request->validate([
            'network' => ['required', 'in:MTN,AIRTEL'],
            'phone'   => ['required', 'string', 'regex:/^(\+?256|0)7\d{8}$/'],
        ], [
            'phone.regex' => __('classy-fashion::app.mobilemoney.phone_invalid'),
        ]);

        $cart = Cart::getCart();

        if (! $cart || $cart->payment?->method !== 'mobilemoney') {
            return redirect()->route('shop.checkout.cart.index');
        }

        $txRef = 'CFH-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));

        $attempt = PaymentAttempt::create([
            'cart_id'     => $cart->id,
            'customer_id' => $cart->customer_id,
            'tx_ref'   => $txRef,
            'amount'   => $cart->grand_total,
            'currency' => $cart->cart_currency_code,
            'network'  => $request->input('network'),
            'status'   => PaymentAttempt::STATUS_PENDING,
        ]);

        // Only this browser session may follow, poll or finalise the attempt.
        session()->push('classy.mobilemoney.attempts', $attempt->public_id);

        $result = Flutterwave::chargeUgandaMobileMoney([
            'tx_ref'       => $txRef,
            'amount'       => (string) $cart->grand_total,
            'currency'     => $cart->cart_currency_code,
            'network'      => $request->input('network'),
            'email'        => $cart->customer_email,
            'phone_number' => $this->msisdn($request->input('phone')),
            'fullname'     => trim($cart->customer_first_name.' '.$cart->customer_last_name),
            'redirect_url' => route('classy.mobilemoney.return', ['attempt' => $attempt->public_id]),
        ]);

        $data = $result['data']['data'] ?? [];

        if (! $result['ok'] || empty($data['id'])) {
            $attempt->update([
                'status'         => PaymentAttempt::STATUS_FAILED,
                'failure_reason' => substr((string) ($result['data']['message'] ?? 'Charge rejected'), 0, 500),
            ]);

            return redirect()->route('shop.checkout.cart.index')
                ->with('error', __('classy-fashion::app.mobilemoney.charge_failed'));
        }

        $attempt->update(['gateway_tx_id' => (string) $data['id']]);

        return redirect()->route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]);
    }

    /**
     * Approval-status page. Polls the gateway server-side; a successful
     * verification finalizes (creates) the paid order here too, so the
     * demo works without a public webhook URL.
     */
    public function status(PaymentAttempt $attempt)
    {
        abort_unless($this->owns($attempt), 404);

        $final = $this->checkAttempt($attempt);

        if ($final === PaymentAttempt::STATUS_SUCCESS) {
            // The order may have been created by the webhook first; the shop's
            // success page reads the order id from the session.
            session()->flash('order_id', $attempt->fresh()->order_id);

            return redirect()->route('shop.checkout.onepage.success');
        }

        $message = match ($final) {
            PaymentAttempt::STATUS_FAILED           => 'payment_failed',
            PaymentAttempt::STATUS_EXPIRED          => 'payment_expired',
            PaymentAttempt::STATUS_PAID_UNFULFILLED => 'payment_unfulfilled',
            default                                 => null,
        };

        if ($message) {
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', __("classy-fashion::app.mobilemoney.{$message}", ['ref' => $attempt->tx_ref]));
        }

        return view('classy-fashion::shop.mobilemoney.status', ['attempt' => $attempt->fresh()]);
    }

    /**
     * Harmless landing for gateway redirects. Never creates orders.
     */
    public function return(PaymentAttempt $attempt)
    {
        abort_unless($this->owns($attempt), 404);

        return redirect()->route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]);
    }

    /**
     * Gateway webhook. Signature-checked; amounts verified server-side.
     */
    public function webhook(Request $request)
    {
        if (! Flutterwave::webhookSignatureValid($request->header('verif-hash'))) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $data = (array) $request->input('data', []);

        $attempt = PaymentAttempt::where('tx_ref', $data['tx_ref'] ?? null)->first();

        if (! $attempt || ! $attempt->isOpen()) {
            return response()->json(['message' => 'Nothing to do.']);
        }

        $final = $this->checkAttempt($attempt, ! empty($data['id']) ? (string) $data['id'] : null);

        return response()->json(['message' => $final]);
    }

    /**
     * Is this attempt one this browser session started?
     */
    protected function owns(PaymentAttempt $attempt): bool
    {
        return in_array($attempt->public_id, (array) session('classy.mobilemoney.attempts', []), true);
    }

    /**
     * Uganda mobile number as an international MSISDN without "+": 2567XXXXXXXX.
     */
    protected function msisdn(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return str_starts_with($digits, '256') ? $digits : '256'.ltrim($digits, '0');
    }

    /**
     * Verify one attempt server-side and finalize it.
     *
     * @return string pending|expired|failed|finalizing|success|paid_unfulfilled
     */
    protected function checkAttempt(PaymentAttempt $attempt, ?string $gatewayTxId = null): string
    {
        $attempt->refresh();

        // Done, failed, or being finalised by another request.
        if (! $attempt->isOpen()) {
            return $attempt->status;
        }

        $gatewayTxId ??= $attempt->gateway_tx_id;

        if (! $gatewayTxId) {
            return $this->stillWaiting($attempt);
        }

        $result = Flutterwave::verifyTransaction($gatewayTxId);

        $data = $result['ok'] ? (array) ($result['data']['data'] ?? []) : [];

        $verified = ($data['status'] ?? null) === 'successful'
            && ($data['tx_ref'] ?? null) === $attempt->tx_ref
            && (float) ($data['amount'] ?? -1) === (float) $attempt->amount
            && ($data['currency'] ?? null) === $attempt->currency;

        if ($verified) {
            return $this->finalize($attempt, $data);
        }

        if (($data['status'] ?? null) === 'failed') {
            $attempt->update([
                'status'         => PaymentAttempt::STATUS_FAILED,
                'failure_reason' => 'Gateway reported failure.',
            ]);

            return PaymentAttempt::STATUS_FAILED;
        }

        if (($data['status'] ?? null) === 'successful') {
            // Paid, but not for this reference/amount/currency: never an order.
            Log::warning('Mobile money verification mismatch', ['attempt' => $attempt->id, 'tx_ref' => $attempt->tx_ref]);
        }

        return $this->stillWaiting($attempt);
    }

    protected function stillWaiting(PaymentAttempt $attempt): string
    {
        if ($attempt->status === PaymentAttempt::STATUS_PENDING && $attempt->isStale()) {
            $attempt->update(['status' => PaymentAttempt::STATUS_EXPIRED]);

            return PaymentAttempt::STATUS_EXPIRED;
        }

        return $attempt->status;
    }

    /**
     * Exactly one request may create the order for a verified payment.
     *
     * The webhook and the customer's "Check Status" can arrive together. The
     * single UPDATE below flips pending/expired -> finalizing atomically; the
     * request that changes the row creates the order, every other request
     * sees it taken and does nothing. (A DB transaction is not used here:
     * Bagisto commits inside OrderRepository::create, which would release a
     * row lock early.)
     */
    protected function finalize(PaymentAttempt $attempt, array $gatewayData): string
    {
        $claimed = PaymentAttempt::query()
            ->whereKey($attempt->getKey())
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_EXPIRED])
            ->update([
                'status'          => PaymentAttempt::STATUS_FINALIZING,
                'verified_at'     => now(),
                'gateway_payload' => json_encode($gatewayData),
            ]);

        if ($claimed === 0) {
            return $attempt->fresh()->status;
        }

        return $this->createPaidOrder($attempt->refresh());
    }

    /**
     * Create the paid order from the cart. Runs only after verification and
     * only for the request that won the claim.
     *
     * @return string success|paid_unfulfilled
     */
    protected function createPaidOrder(PaymentAttempt $attempt): string
    {
        $cart = \Webkul\Checkout\Models\Cart::find($attempt->cart_id);

        if (! $cart || $cart->is_active === false) {
            return $this->unfulfilled($attempt, 'Cart no longer available after payment.');
        }

        try {
            Cart::setCart($cart);
            Cart::collectTotals();

            $order = $this->orderRepository->create((new OrderResource($cart))->jsonSerialize());

            $this->orderRepository->updateOrderStatus($order, \ClassyFashion\Models\Sales\Order::STATUS_PAID);
        } catch (\Throwable $e) {
            report($e);

            return $this->unfulfilled($attempt, 'Order creation failed after payment.');
        }

        Audit::log(
            $order->fresh(),
            "Order #{$order->increment_id} paid via mobile money ({$attempt->network}, {$attempt->tx_ref})",
            ['tx_ref' => $attempt->tx_ref, 'network' => $attempt->network, 'to' => 'paid'],
            null,
            'order.payment'
        );

        $attempt->update(['status' => PaymentAttempt::STATUS_SUCCESS, 'order_id' => $order->id]);

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

        return PaymentAttempt::STATUS_SUCCESS;
    }

    /**
     * The customer's money was verified but no order exists. Never report
     * this as a plain failure: record it so staff can fix or refund it.
     */
    protected function unfulfilled(PaymentAttempt $attempt, string $reason): string
    {
        $attempt->update([
            'status'         => PaymentAttempt::STATUS_PAID_UNFULFILLED,
            'failure_reason' => $reason,
        ]);

        Audit::log(
            $attempt,
            "Payment {$attempt->tx_ref} verified but no order was created: {$reason}",
            ['tx_ref' => $attempt->tx_ref, 'amount' => $attempt->amount, 'network' => $attempt->network],
            null,
            'order.payment_unfulfilled'
        );

        return PaymentAttempt::STATUS_PAID_UNFULFILLED;
    }

    public static function routes(): void
    {
        Route::middleware('web')->prefix('classy/mobilemoney')->name('classy.mobilemoney.')->group(function () {
            Route::get('redirect', [self::class, 'redirect'])->name('redirect');
            Route::post('charge', [self::class, 'charge'])->middleware('throttle:5,1')->name('charge');
            Route::get('status/{attempt}', [self::class, 'status'])->name('status');
            Route::get('return/{attempt}', [self::class, 'return'])->name('return');

            Route::post('webhook', [self::class, 'webhook'])
                ->withoutMiddleware(VerifyCsrfToken::class)
                ->name('webhook');
        });
    }
}
