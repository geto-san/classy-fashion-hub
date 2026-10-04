<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MTN Mobile Money (Uganda) via the MoMo developer API.
 *
 * Sandbox keys are self-service at momodeveloper.mtn.com (email signup,
 * subscribe to Collection, create an API user) — no business documents.
 * Trust comes from the server-side status query, never the callback body.
 */
class MtnMomoProvider implements MobileMoneyProvider
{
    public function code(): string
    {
        return 'mtn';
    }

    public function isConfigured(): bool
    {
        return filled(config('classy.momo.mtn.subscription_key'))
            && filled(config('classy.momo.mtn.api_user_id'))
            && filled(config('classy.momo.mtn.api_key'));
    }

    public function charge(PaymentAttempt $attempt, array $customer): array
    {
        $token = $this->accessToken();

        if (! $token) {
            return ['ok' => false, 'gateway_tx_id' => null, 'message' => 'Could not reach MTN.'];
        }

        $reference = (string) Str::uuid();

        $response = Http::baseUrl($this->base())
            ->withToken($token)
            ->withHeaders([
                'X-Reference-Id'      => $reference,
                'X-Target-Environment' => $this->environment(),
                'Ocp-Apim-Subscription-Key' => (string) config('classy.momo.mtn.subscription_key'),
            ])
            ->acceptJson()
            ->timeout(30)
            ->post('/collection/v1_0/requesttopay', [
                'amount'       => (string) $attempt->amount,
                'currency'     => (string) config('classy.momo.mtn.currency', 'UGX'),
                'externalId'   => $attempt->tx_ref,
                'payer'        => [
                    'partyIdType' => 'MSISDN',
                    'partyId'     => $customer['phone'],
                ],
                'payerMessage'  => 'Classy Fashion Hub order',
                'payeeNote'     => 'Classy Fashion Hub order '.$attempt->tx_ref,
                'callbackUrl'   => route('classy.mobilemoney.mtn-callback'),
            ]);

        if (! $response->successful() || $response->status() !== 202) {
            Log::warning('classy.momo.charge_failed', ['status' => $response->status()]);

            return ['ok' => false, 'gateway_tx_id' => null, 'message' => 'MTN rejected the charge.'];
        }

        return ['ok' => true, 'gateway_tx_id' => $reference, 'message' => null];
    }

    public function verify(PaymentAttempt $attempt): array
    {
        $token = $this->accessToken();

        if (! $token || ! $attempt->gateway_tx_id) {
            return ['ok' => false, 'successful' => false, 'failed' => false];
        }

        $response = Http::baseUrl($this->base())
            ->withToken($token)
            ->withHeaders([
                'X-Target-Environment' => $this->environment(),
                'Ocp-Apim-Subscription-Key' => (string) config('classy.momo.mtn.subscription_key'),
            ])
            ->acceptJson()
            ->timeout(30)
            ->get("/collection/v1_0/requesttopay/{$attempt->gateway_tx_id}");

        if (! $response->successful()) {
            return ['ok' => false, 'successful' => false, 'failed' => false];
        }

        $status = strtoupper((string) ($response->json('status') ?? ''));

        return [
            'ok'         => true,
            'successful' => $status === 'SUCCESSFUL'
                && (string) ($response->json('externalId') ?? '') === $attempt->tx_ref
                && (float) ($response->json('amount') ?? -1) === (float) $attempt->amount,
            'failed'     => $status === 'FAILED',
        ];
    }

    public function webhookIsValid(Request $request): bool
    {
        // MTN callbacks carry no signature; the verify call is the proof.
        return true;
    }

    public function attemptFromCallback(Request $request): ?PaymentAttempt
    {
        $ref = $request->input('externalId') ?? $request->input('external_id');

        if (! $ref) {
            return null;
        }

        return PaymentAttempt::where('tx_ref', $ref)->first();
    }

    protected function base(): string
    {
        return rtrim((string) config('classy.momo.mtn.base_url', 'https://sandbox.momodeveloper.mtn.com'), '/');
    }

    protected function environment(): string
    {
        return (string) config('classy.momo.mtn.environment', 'sandbox');
    }

    protected function accessToken(): ?string
    {
        $response = Http::baseUrl($this->base())
            ->withBasicAuth(
                (string) config('classy.momo.mtn.api_user_id'),
                (string) config('classy.momo.mtn.api_key')
            )
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => (string) config('classy.momo.mtn.subscription_key'),
            ])
            ->acceptJson()
            ->timeout(30)
            ->post('/collection/token/');

        if (! $response->successful()) {
            Log::warning('classy.momo.token_failed', ['status' => $response->status()]);

            return null;
        }

        return $response->json('access_token');
    }
}
