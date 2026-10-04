<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Models\PaymentAttempt;
use ClassyFashion\Support\Flutterwave;
use Illuminate\Http\Request;

/**
 * Flutterwave v3 behind the provider contract. Behaviour is unchanged:
 * signature-checked webhook plus amount/currency/reference verification.
 */
class FlutterwaveProvider implements MobileMoneyProvider
{
    public function code(): string
    {
        return 'flutterwave';
    }

    public function isConfigured(): bool
    {
        return Flutterwave::configured();
    }

    public function charge(PaymentAttempt $attempt, array $customer): array
    {
        $result = Flutterwave::chargeUgandaMobileMoney([
            'tx_ref'       => $attempt->tx_ref,
            'amount'       => (string) $attempt->amount,
            'currency'     => $attempt->currency,
            'network'      => $attempt->network,
            'email'        => $customer['email'] ?? null,
            'phone_number' => $customer['phone'] ?? null,
            'fullname'     => $customer['name'] ?? '',
            'redirect_url' => $customer['redirect_url'] ?? null,
        ]);

        $data = $result['data']['data'] ?? [];

        if (! $result['ok'] || empty($data['id'])) {
            return [
                'ok'            => false,
                'gateway_tx_id' => null,
                'message'       => substr((string) ($result['data']['message'] ?? 'Charge rejected'), 0, 500),
            ];
        }

        return ['ok' => true, 'gateway_tx_id' => (string) $data['id'], 'message' => null];
    }

    public function verify(PaymentAttempt $attempt): array
    {
        if (! $attempt->gateway_tx_id) {
            return ['ok' => false, 'successful' => false, 'failed' => false];
        }

        $result = Flutterwave::verifyTransaction($attempt->gateway_tx_id);

        $data = $result['ok'] ? (array) ($result['data']['data'] ?? []) : [];

        if (($data['status'] ?? null) === 'failed') {
            return ['ok' => true, 'successful' => false, 'failed' => true];
        }

        $successful = ($data['status'] ?? null) === 'successful'
            && ($data['tx_ref'] ?? null) === $attempt->tx_ref
            && (float) ($data['amount'] ?? -1) === (float) $attempt->amount
            && ($data['currency'] ?? null) === $attempt->currency;

        return ['ok' => $result['ok'], 'successful' => $successful, 'failed' => false];
    }

    public function webhookIsValid(Request $request): bool
    {
        return Flutterwave::webhookSignatureValid($request->header('verif-hash'));
    }

    public function attemptFromCallback(Request $request): ?PaymentAttempt
    {
        $data = (array) $request->input('data', []);

        if (empty($data['tx_ref'])) {
            return null;
        }

        return PaymentAttempt::where('tx_ref', $data['tx_ref'])->first();
    }
}
