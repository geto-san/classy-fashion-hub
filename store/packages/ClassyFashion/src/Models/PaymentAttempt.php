<?php

namespace ClassyFashion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Mobile-money payment attempt (report 9.6).
 *
 * Keeps pending, successful, failed and unfulfilled payments
 * distinguishable with their gateway transaction reference, even when no
 * order results.
 *
 * Life cycle:
 *   pending -> finalizing -> success
 *                         -> paid_unfulfilled   (paid, but no order could be made)
 *   pending -> failed | expired                 (expired can still be paid late)
 */
class PaymentAttempt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FINALIZING = 'finalizing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PAID_UNFULFILLED = 'paid_unfulfilled';

    protected $table = 'classy_payment_attempts';

    protected $fillable = [
        'public_id',
        'cart_id',
        'customer_id',
        'order_id',
        'tx_ref',
        'gateway_tx_id',
        'amount',
        'currency',
        'network',
        'provider',
        'status',
        'failure_reason',
        'customer_claim',
        'verified_at',
        'expires_at',
        'gateway_payload',
    ];

    protected $casts = [
        'verified_at'     => 'datetime',
        'expires_at'      => 'datetime',
        'gateway_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $attempt) {
            if (empty($attempt->public_id)) {
                $attempt->public_id = (string) Str::uuid();
            }

            if (empty($attempt->expires_at)) {
                $attempt->expires_at = now()->addMinutes((int) config('classy.momo.pending_minutes', 30));
            }
        });
    }

    /**
     * URLs use the unguessable public id, never the sequential id.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Still waiting for, or able to receive, a verified payment.
     * (An expired prompt can still be approved late; that must not be lost.)
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_EXPIRED], true);
    }

    public function isStale(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Payments that need a person: money taken but no order, or a
     * finalisation that never finished.
     */
    public function needsAttention(): bool
    {
        return $this->status === self::STATUS_PAID_UNFULFILLED
            || ($this->status === self::STATUS_FINALIZING && $this->updated_at?->lt(now()->subMinutes(5)));
    }
}
