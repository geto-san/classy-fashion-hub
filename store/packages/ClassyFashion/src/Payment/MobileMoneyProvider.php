<?php

namespace ClassyFashion\Payment;

use ClassyFashion\Models\PaymentAttempt;
use Illuminate\Http\Request;

/**
 * Mobile-money provider contract (report 9.6).
 *
 * Every provider initiates charges and verifies them server-side.
 * Trust never comes from a browser redirect or an unsigned callback —
 * only from a verify call made with our keys.
 */
interface MobileMoneyProvider
{
    public function code(): string;

    public function isConfigured(): bool;

    /**
     * @return array{ok: bool, gateway_tx_id: ?string, message: ?string}
     */
    public function charge(PaymentAttempt $attempt, array $customer): array;

    /**
     * @return array{ok: bool, successful: bool, failed: bool}
     */
    public function verify(PaymentAttempt $attempt): array;

    public function webhookIsValid(Request $request): bool;

    /**
     * Find the attempt a callback refers to, if any.
     */
    public function attemptFromCallback(Request $request): ?PaymentAttempt;
}
