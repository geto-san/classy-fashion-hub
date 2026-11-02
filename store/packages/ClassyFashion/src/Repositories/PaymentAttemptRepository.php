<?php

namespace ClassyFashion\Repositories;

use ClassyFashion\Models\PaymentAttempt;

/**
 * Persist payment attempts while retaining the atomic finalization claim.
 */
class PaymentAttemptRepository
{
    /**
     * Create the attempt using its existing model hooks and fingerprint capture.
     */
    public function create(array $attributes): PaymentAttempt
    {
        return PaymentAttempt::create($attributes);
    }

    /**
     * Apply normal model casts and events to an attempt update.
     */
    public function update(PaymentAttempt $attempt, array $attributes): bool
    {
        return $attempt->update($attributes);
    }

    /**
     * Refresh the same instance before checking concurrent payment state.
     */
    public function refresh(PaymentAttempt $attempt): PaymentAttempt
    {
        return $attempt->refresh();
    }

    /**
     * Retrieve a separate current instance after another operation may have changed it.
     */
    public function fresh(PaymentAttempt $attempt): ?PaymentAttempt
    {
        return $attempt->fresh();
    }

    /**
     * Exactly one callback or browser poll may claim a verified payment for order creation.
     */
    public function claimFinalization(PaymentAttempt $attempt, array $gatewayData): int
    {
        return PaymentAttempt::query()
            ->whereKey($attempt->getKey())
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_EXPIRED])
            ->update([
                'status' => PaymentAttempt::STATUS_FINALIZING,
                'verified_at' => now(),
                'gateway_payload' => json_encode($gatewayData),
            ]);
    }
}
