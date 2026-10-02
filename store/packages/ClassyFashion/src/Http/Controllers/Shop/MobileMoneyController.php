<?php

namespace ClassyFashion\Http\Controllers\Shop;

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Support\Audit;
use ClassyFashion\Support\Flutterwave;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
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
            'phone'   => ['required', 'string', 'max:20'],
        ]);

        $cart = Cart::getCart();

        if (! $cart || $cart->payment?->method !== 'mobilemoney') {
            return redirect()->route('shop.checkout.cart.index');
        }

        $txRef = 'CFH-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));

        $attempt = PaymentAttempt::create([
            'cart_id'  => $cart->id,
            'tx_ref'   => $txRef,
            'amount'   => $cart->grand_total,
            'currency' => $cart->cart_currency_code,
            'network'  => $request->input('network'),
            'status'   => PaymentAttempt::STATUS_PENDING,
        ]);

        $result = Flutterwave::chargeUgandaMobileMoney([
            'tx_ref'       => $txRef,
            'amount'       => (string) $cart->grand_total,
            'currency'     => $cart->cart_currency_code,
            'network'      => $request->input('network'),
            'email'        => $cart->customer_email,
            'phone_number' => $request->input('phone'),
            'fullname'     => trim($cart->customer_first_name.' '.$cart->customer_last_name),
            'redirect_url' => route('classy.mobilemoney.return', ['attempt' => $attempt->id]),
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

        return redirect()->route('classy.mobilemoney.status', ['attempt' => $attempt->id]);
    }

    /**
     * Approval-status page. Polls the gateway server-side; a successful
     * verification finalizes (creates) the paid order here too, so the
     * demo works without a public webhook URL.
     */
    public function status(PaymentAttempt $attempt)
    {
        $final = $this->checkAttempt($attempt);

        if ($final === 'success') {
            return redirect()->route('shop.checkout.onepage.success');
        }

        if ($final === 'failed') {
            return redirect()->route('shop.checkout.cart.index')
                ->with('error', __('classy-fashion::app.mobilemoney.payment_failed'));
        }

        return view('classy-fashion::shop.mobilemoney.status', ['attempt' => $attempt->fresh()]);
    }

    /**
     * Harmless landing for gateway redirects. Never creates orders.
     */
    public function return(PaymentAttempt $attempt)
    {
        return redirect()->route('classy.mobilemoney.status', ['attempt' => $attempt->id]);
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

        if (! $attempt || $attempt->status !== PaymentAttempt::STATUS_PENDING) {
            return response()->json(['message' => 'Nothing to do.']);
        }

        $final = $this->checkAttempt($attempt, (string) ($data['id'] ?? ''));

        return response()->json(['message' => $final]);
    }

    /**
     * Verify one attempt server-side and finalize it.
     *
     * @return string success|failed|pending
     */
    protected function checkAttempt(PaymentAttempt $attempt, ?string $gatewayTxId = null): string
    {
        $attempt->refresh();

        if ($attempt->status !== PaymentAttempt::STATUS_PENDING) {
            return $attempt->status;
        }

        $gatewayTxId ??= $attempt->gateway_tx_id;

        if (! $gatewayTxId) {
            return PaymentAttempt::STATUS_PENDING;
        }

        $result = Flutterwave::verifyTransaction($gatewayTxId);

        $data = $result['ok'] ? (array) ($result['data']['data'] ?? []) : [];

        if (
            ($data['status'] ?? null) !== 'successful'
            || ($data['tx_ref'] ?? null) !== $attempt->tx_ref
            || (float) ($data['amount'] ?? -1) !== (float) $attempt->amount
            || ($data['currency'] ?? null) !== $attempt->currency
        ) {
            if (($data['status'] ?? null) === 'failed') {
                $attempt->update([
                    'status'         => PaymentAttempt::STATUS_FAILED,
                    'failure_reason' => 'Gateway reported failure.',
                ]);

                return PaymentAttempt::STATUS_FAILED;
            }

            return PaymentAttempt::STATUS_PENDING;
        }

        return $this->createPaidOrder($attempt) ? PaymentAttempt::STATUS_SUCCESS : PaymentAttempt::STATUS_FAILED;
    }

    /**
     * Create the paid order from the cart. Runs only after verification.
     */
    protected function createPaidOrder(PaymentAttempt $attempt): bool
    {
        $cart = \Webkul\Checkout\Models\Cart::find($attempt->cart_id);

        if (! $cart || $cart->is_active === false) {
            $attempt->update([
                'status'         => PaymentAttempt::STATUS_FAILED,
                'failure_reason' => 'Cart no longer available.',
            ]);

            return false;
        }

        Cart::setCart($cart);
        Cart::collectTotals();

        $data = (new OrderResource($cart))->jsonSerialize();

        try {
            $order = $this->orderRepository->create($data);
        } catch (\Throwable $e) {
            report($e);

            $attempt->update([
                'status'         => PaymentAttempt::STATUS_FAILED,
                'failure_reason' => 'Order creation failed after payment.',
            ]);

            return false;
        }

        $this->orderRepository->updateOrderStatus($order, \ClassyFashion\Models\Sales\Order::STATUS_PAID);

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

        return true;
    }

    public static function routes(): void
    {
        Route::middleware('web')->prefix('classy/mobilemoney')->name('classy.mobilemoney.')->group(function () {
            Route::get('redirect', [self::class, 'redirect'])->name('redirect');
            Route::post('charge', [self::class, 'charge'])->name('charge');
            Route::get('status/{attempt}', [self::class, 'status'])->name('status');
            Route::get('return/{attempt}', [self::class, 'return'])->name('return');

            Route::post('webhook', [self::class, 'webhook'])
                ->withoutMiddleware(VerifyCsrfToken::class)
                ->name('webhook');
        });
    }
}
