<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Models\PaymentAttempt;
use Illuminate\Console\Command;

/**
 * Mark mobile-money prompts that were never approved as expired (9.6).
 * An expired attempt can still be finalised if the payment arrives late.
 */
class ExpirePayments extends Command
{
    protected $signature = 'classy:expire-payments';

    protected $description = 'Expire mobile-money attempts that were never approved';

    public function handle(): int
    {
        $count = PaymentAttempt::query()
            ->where('status', PaymentAttempt::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => PaymentAttempt::STATUS_EXPIRED]);

        $this->info("Expired {$count} stale payment attempt(s).");

        return self::SUCCESS;
    }
}
