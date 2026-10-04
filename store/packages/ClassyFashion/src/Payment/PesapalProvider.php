<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pesapal API 3.0 (Uganda: MTN/Airtel via the Pesapal iframe).
 *
 * Endpoints crawled from developer.pesapal.com (API 3.0 JSON):
 *  Auth ......... POST {base}/api/Auth/RequestToken {consumer_key, consumer_secret}
 *  Submit order . POST {base}/api/Transactions/SubmitOrderRequest (Bearer)
 *  Status ....... GET  {base}/api/Transactions/GetTransactionStatus?orderTrackingId=
 *  Register IPN . POST {base}/api/URLSetup/RegisterIPN {url, ipn_notification_type}
 * Sandbox base: https://cybqa.pesapal.com/pesapalv3
 * Live base: .... https://pay.pesapal.com/v3
 *
 * Keys: the dashboard "Primary Key" IS consumer_key and the "Secondary Key"
 * IS consumer_secret — there is no separate API key. Public demo keys live
 * at developer.pesapal.com/api3-demo-keys.txt (sandbox play only).
 *
 * Pesapal is a redirect flow: the customer pays on Pesapal's page, so
 * charge() returns a redirect_url and the browser leaves our site. Trust
 * still comes only from GetTransactionStatus, never from the callback.
 */
class PesapalProvider implements MobileMoneyProvider
{
    public function code(): string
    {
        return 'pesapal';
    }

    public function isConfigured(): bool
    {
        return filled(config('classy.momo.pesapal.consumer_key'))
            && filled(config('classy.momo.pesapal.consumer_secret'));
    }

    public function charge(PaymentAttempt $attempt, array $customer): array
    {
        $token = $this->accessToken();

        if (! $token) {
            return ['ok' => false, 'gateway_tx_id' => null, 'message' => 'Could not reach Pesapal.', 'redirect_url' => null];
        }

        $response = Http::baseUrl($this->base())
            ->withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post('/api/Transactions/SubmitOrderRequest', [
                'id'              => substr($attempt->tx_ref, 0, 50),
                'currency'        => $attempt->currency,
                'amount'          => (float) $attempt->amount,
                'description'     => substr('Classy Fashion Hub order '.$attempt->tx_ref, 0, 100),
                'callback_url'    => route('classy.mobilemoney.pesapal-return'),
                'notification_id' => (string) config('classy.momo.pesapal.ipn_id'),
                'billing_address' => array_filter([
                    'email_address' => $customer['email'] ?? null,
                    'phone_number'  => $customer['phone'] ?? null,
                    'country_code'  => 'UG',
                    'first_name'    => $customer['first_name'] ?? null,
                    'last_name'     => $customer['last_name'] ?? null,
                    'city'          => $customer['city'] ?? null,
                ]),
            ]);

        $data = $response->successful() ? (array) $response->json() : [];

        if (empty($data['order_tracking_id']) || empty($data['redirect_url'])) {
            Log::warning('classy.pesapal.charge_failed', ['status' => $response->status()]);

            return ['ok' => false, 'gateway_tx_id' => null, 'message' => 'Pesapal rejected the order.', 'redirect_url' => null];
        }

        return [
            'ok'            => true,
            'gateway_tx_id' => (string) $data['order_tracking_id'],
            'message'       => null,
            'redirect_url'  => (string) $data['redirect_url'],
        ];
    }

    public function verify(PaymentAttempt $attempt): array
    {
        $token = $this->accessToken();

        if (! $token || ! $attempt->gateway_tx_id) {
            return ['ok' => false, 'successful' => false, 'failed' => false];
        }

        $response = Http::baseUrl($this->base())
            ->withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get('/api/Transactions/GetTransactionStatus', [
                'orderTrackingId' => $attempt->gateway_tx_id,
            ]);

        if (! $response->successful()) {
            return ['ok' => false, 'successful' => false, 'failed' => false];
        }

        $data = (array) $response->json();

        $code = (int) ($data['status_code'] ?? -1);

        if ($code === 2) {
            return ['ok' => true, 'successful' => false, 'failed' => true];
        }

        $successful = $code === 1
            && ($data['merchant_reference'] ?? null) === $attempt->tx_ref
            && (float) ($data['amount'] ?? -1) === (float) $attempt->amount
            && ($data['currency'] ?? null) === $attempt->currency;

        return ['ok' => true, 'successful' => $successful, 'failed' => false];
    }

    public function webhookIsValid(Request $request): bool
    {
        // Callback and IPN carry no signature; the status query is the proof.
        return true;
    }

    public function attemptFromCallback(Request $request): ?PaymentAttempt
    {
        $tracking = $request->input('OrderTrackingId');
        $merchant = $request->input('OrderMerchantReference');

        if ($tracking) {
            $attempt = PaymentAttempt::where('gateway_tx_id', $tracking)->first();

            if ($attempt) {
                return $attempt;
            }
        }

        if ($merchant) {
            return PaymentAttempt::where('tx_ref', $merchant)->first();
        }

        return null;
    }

    protected function base(): string
    {
        if (filter_var(config('classy.momo.pesapal.sandbox', true), FILTER_VALIDATE_BOOLEAN)) {
            return rtrim((string) (config('classy.momo.pesapal.sandbox_base') ?: 'https://cybqa.pesapal.com/pesapalv3'), '/');
        }

        return rtrim((string) (config('classy.momo.pesapal.live_base') ?: 'https://pay.pesapal.com/v3'), '/');
    }

    protected function accessToken(): ?string
    {
        return Cache::remember('classy.pesapal.token', 240, function () {
            $response = Http::baseUrl($this->base())
                ->acceptJson()
                ->timeout(30)
                ->post('/api/Auth/RequestToken', [
                    'consumer_key'    => (string) config('classy.momo.pesapal.consumer_key'),
                    'consumer_secret' => (string) config('classy.momo.pesapal.consumer_secret'),
                ]);

            if (! $response->successful()) {
                Log::warning('classy.pesapal.token_failed', ['status' => $response->status()]);

                return null;
            }

            return $response->json('token');
        });
    }
}
