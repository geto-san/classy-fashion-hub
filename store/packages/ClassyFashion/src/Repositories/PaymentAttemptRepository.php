<?php

namespace ClassyFashion\Repositories;

use ClassyFashion\Models\PaymentAttempt;
use Webkul\Checkout\Repositories\CartRepository;

/**
 * Persist payment attempts while retaining the atomic finalization claim.
 */
class PaymentAttemptRepository
{
    /**
     * Claim a manually confirmed attempt exactly once.
     */
    public function claimManual(PaymentAttempt $attempt): int
    {
        return PaymentAttempt::query()->whereKey($attempt->id)
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_EXPIRED])
            ->update(['status' => PaymentAttempt::STATUS_FINALIZING, 'verified_at' => now()]);
    }

    /**
     * Reserve a cart for one successful payment finalization.
     */
    public function claimCart(int $cartId): int
    {
        return app(CartRepository::class)->getModel()
            ->whereKey($cartId)->where('is_active', 1)->update(['is_active' => 0]);
    }

    /**
     * Preserve terminal outcomes when two practice-payment actions race.
     */
    public function practiceOutcome(PaymentAttempt $attempt, string $result): int
    {
        return PaymentAttempt::query()->whereKey($attempt->id)->where('provider', 'coursework')
            ->where('status', PaymentAttempt::STATUS_PENDING)
            ->update(['gateway_payload' => json_encode(['coursework_result' => $result])]);
    }

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
