<?php

namespace ClassyFashion\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mobile-money payment attempt (report 9.6).
 *
 * Keeps pending, successful and failed payments distinguishable with
 * their gateway transaction reference, even when no order results.
 */
class PaymentAttempt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $table = 'classy_payment_attempts';

    protected $fillable = [
        'cart_id',
        'order_id',
        'tx_ref',
        'gateway_tx_id',
        'amount',
        'currency',
        'network',
        'status',
        'failure_reason',
    ];
}
