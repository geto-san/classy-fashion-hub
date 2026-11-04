<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Models\PaymentAttempt;
use Illuminate\Http\Request;

/**
 * Exercise checkout outcomes locally without contacting a payment gateway.
 */
class CourseworkProvider implements MobileMoneyProvider
{
    /**
     * Keep practice transactions identifiable in stored payment records.
     */
    public function code(): string
    {
        return 'coursework';
    }

    /**
     * Practice payments require the explicit project mode.
     */
    public function isConfigured(): bool
    {
        return (bool) config('classy.coursework_mode');
    }

    /**
     * Issue a local reference without making a network request.
     */
    public function charge(PaymentAttempt $attempt, array $customer): array
    {
        return [
            'ok' => $this->isConfigured(),
            'gateway_tx_id' => $this->isConfigured() ? 'COURSE-'.$attempt->public_id : null,
            'message' => null,
        ];
    }

    /**
     * Read the outcome selected by the session that owns this attempt.
     */
    public function verify(PaymentAttempt $attempt): array
    {
        $enabled = $this->isConfigured() && $attempt->provider === $this->code();
        $result = $attempt->gateway_payload['coursework_result'] ?? null;

        return [
            'ok' => $enabled,
            'successful' => $enabled && $result === 'success',
            'failed' => $enabled && $result === 'failed',
        ];
    }

    /**
     * External callbacks cannot authorize practice payments.
     */
    public function webhookIsValid(Request $request): bool
    {
        return false;
    }

    /**
     * Practice outcomes originate only from the authenticated browser flow.
     */
    public function attemptFromCallback(Request $request): ?PaymentAttempt
    {
        return null;
    }
}
