<?php

namespace ClassyFashion\Models\Sales;

use Webkul\Sales\Models\Order as BaseOrder;

/**
 * Classy Fashion Hub order: allowed manual status transitions (report 9.5/9.7).
 *
 * Status codes and labels live on the core order (small documented core
 * addition); this subclass adds the transition map used by our admin
 * actions and is what OrderProxy resolves to for relations.
 */
class Order extends BaseOrder
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PAID = 'paid';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    /**
     * Allowed manual transitions (report 9.5/9.7 flow).
     *
     * pending -> confirmed -> paid -> processing -> dispatched -> delivered.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING         => [self::STATUS_CONFIRMED, self::STATUS_PAID, self::STATUS_CANCELED],
        self::STATUS_PENDING_PAYMENT => [self::STATUS_PAID, self::STATUS_CANCELED],
        self::STATUS_CONFIRMED       => [self::STATUS_PAID, self::STATUS_CANCELED],
        self::STATUS_PAID            => [self::STATUS_PROCESSING, self::STATUS_CANCELED],
        self::STATUS_PROCESSING      => [self::STATUS_DISPATCHED, self::STATUS_CANCELED],
        self::STATUS_DISPATCHED      => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED       => [],
        self::STATUS_CANCELED        => [],
    ];

    protected $statusLabel = [
        self::STATUS_PENDING         => 'Pending',
        self::STATUS_PENDING_PAYMENT => 'Pending Payment',
        self::STATUS_CONFIRMED       => 'Confirmed',
        self::STATUS_PAID            => 'Paid',
        self::STATUS_PROCESSING      => 'Processing',
        self::STATUS_DISPATCHED      => 'Dispatched',
        self::STATUS_DELIVERED       => 'Delivered',
        self::STATUS_COMPLETED       => 'Completed',
        self::STATUS_CANCELED        => 'Canceled',
        self::STATUS_CLOSED          => 'Closed',
        self::STATUS_FRAUD           => 'Fraud',
    ];

    /**
     * Whether a manual transition from the current status is allowed.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
