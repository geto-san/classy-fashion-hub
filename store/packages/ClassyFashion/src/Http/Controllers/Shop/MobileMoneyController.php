<?php

namespace ClassyFashion\Http\Controllers\Shop;

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Support\Audit;
use ClassyFashion\Support\Notify;
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
 * MTN MoMo mobile-money flow (report 9.6) plus the manual Till fallback.
 *
 * The order is created ONLY after server-side verification (status query
 * on our status check, or the MTN callback triggering one). Returning
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

        $network = $request->input('network');

        $provider = \ClassyFashion\Payment\Momo::provider($network);

        $attempt = PaymentAttempt::create([
            'cart_id'     => $cart->id,
            'customer_id' => $cart->customer_id,
            'tx_ref'   => $txRef,
            'amount'   => $cart->grand_total,
            'currency' => $cart->cart_currency_code,
            'network'  => $network,
            'provider' => $provider?->code(),
            'status'   => PaymentAttempt::STATUS_PENDING,
        ]);

        // Only this browser session may follow, poll or finalise the attempt.
        session()->push('classy.mobilemoney.attempts', $attempt->public_id);

        // No API provider (or provider doesn't cover this network): the
        // customer pays the shop Till by phone and claims the reference.
        if (! $provider) {
            return redirect()->route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]);
        }

        $result = $provider->charge($attempt, [
            'email'        => $cart->customer_email,
            'phone'        => $this->msisdn($request->input('phone')),
            'name'         => trim($cart->customer_first_name.' '.$cart->customer_last_name),
            'redirect_url' => route('classy.mobilemoney.return', ['attempt' => $attempt->public_id]),
        ]);

        if (! $result['ok'] || empty($result['gateway_tx_id'])) {
            $attempt->update([
                'status'         => PaymentAttempt::STATUS_FAILED,
                'failure_reason' => substr((string) ($result['message'] ?? 'Charge rejected'), 0, 500),
            ]);

            return redirect()->route('shop.checkout.cart.index')
                ->with('error', __('classy-fashion::app.mobilemoney.charge_failed'));
        }

        $attempt->update(['gateway_tx_id' => (string) $result['gateway_tx_id']]);

        // Prompt flows (MTN, manual Till) wait on the status page.
        if (! empty($result['redirect_url'])) {
            return redirect()->away($result['redirect_url']);
        }

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

        return view('classy-fashion::shop.mobilemoney.status', [
            'attempt' => $attempt->fresh(),
            'till'    => \ClassyFashion\Payment\Momo::tillNumber(),
            'manual'  => $this->providerFor($attempt->fresh()) === null,
        ]);
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
     * MTN status callback. Carries no signature, so the lookup key is only
     * a hint: the verify call is still the proof, as everywhere else.
     */
    public function mtnCallback(Request $request)
    {
        $provider = new \ClassyFashion\Payment\MtnMomoProvider;

        $attempt = $provider->attemptFromCallback($request);

        if (! $attempt || ! $attempt->isOpen()) {
            return response()->json(['message' => 'Nothing to do.']);
        }

        return response()->json(['message' => $this->checkAttempt($attempt)]);
    }

    /**
     * Customer submits their own Till transaction reference after paying
     * by phone. Stored as a claim for staff to confirm; never an order.
     */
    public function claim(Request $request, PaymentAttempt $attempt)
    {
        abort_unless($this->owns($attempt), 404);

        $request->validate([
            'customer_claim' => ['required', 'string', 'max:100'],
        ]);

        $attempt->refresh();

        if (! $attempt->isOpen()) {
            return redirect()->route('classy.mobilemoney.status', ['attempt' => $attempt->public_id]);
        }

        $attempt->update(['customer_claim' => $request->input('customer_claim')]);

        return redirect()->route('classy.mobilemoney.status', ['attempt' => $attempt->public_id])
            ->with('success', __('classy-fashion::app.mobilemoney.claim_saved'));
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

        $provider = $this->providerFor($attempt);

        if (! $gatewayTxId) {
            $gatewayTxId = $attempt->gateway_tx_id;
        }

        if (! $provider || ! $gatewayTxId) {
            return $this->stillWaiting($attempt);
        }

        $result = $provider->verify($attempt->refresh());

        if ($result['successful']) {
            return $this->finalize($attempt, []);
        }

        if ($result['failed']) {
            $attempt->update([
                'status'         => PaymentAttempt::STATUS_FAILED,
                'failure_reason' => 'Gateway reported failure.',
            ]);

            return PaymentAttempt::STATUS_FAILED;
        }

        return $this->stillWaiting($attempt);
    }

    protected function providerFor(PaymentAttempt $attempt): ?\ClassyFashion\Payment\MobileMoneyProvider
    {
        $map = [
            'mtn' => \ClassyFashion\Payment\MtnMomoProvider::class,
        ];

        $class = $map[$attempt->provider ?? ''] ?? null;

        if ($class) {
            return app($class);
        }

        return \ClassyFashion\Payment\Momo::provider($attempt->network);
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
     * only for the request that won the claim. Shared with the manual Till
     * path (see Momo::createPaidOrder).
     *
     * @return string success|paid_unfulfilled
     */
    protected function createPaidOrder(PaymentAttempt $attempt): string
    {
        return \ClassyFashion\Payment\Momo::createPaidOrder($attempt, null);
    }

    /**
     * The customer's money was verified but no order exists. Never report
     * this as a plain failure: record it so staff can fix or refund it.
     */
    protected function unfulfilled(PaymentAttempt $attempt, string $reason): string
    {
        return \ClassyFashion\Payment\Momo::unfulfilled($attempt, $reason, null);
    }

    public static function routes(): void
    {
        Route::middleware('web')->prefix('classy/mobilemoney')->name('classy.mobilemoney.')->group(function () {
            Route::get('redirect', [self::class, 'redirect'])->name('redirect');
            Route::post('charge', [self::class, 'charge'])->middleware('throttle:5,1')->name('charge');
            Route::get('status/{attempt}', [self::class, 'status'])->name('status');
            Route::get('return/{attempt}', [self::class, 'return'])->name('return');

            Route::post('mtn-callback', [self::class, 'mtnCallback'])
                ->withoutMiddleware(VerifyCsrfToken::class)
                ->name('mtn-callback');

            Route::post('claim/{attempt}', [self::class, 'claim'])->name('claim');
        });
    }
}
