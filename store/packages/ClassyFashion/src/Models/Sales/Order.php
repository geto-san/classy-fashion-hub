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

    public function isCashOnDelivery(): bool
    {
        return $this->payment?->method === 'cashondelivery';
    }

    public function usesMobileMoney(): bool
    {
        return $this->payment?->method === 'mobilemoney';
    }

    /**
     * Statuses staff may move this order to by hand.
     *
     * - Cash on delivery is packed and sent before any money changes hands,
     *   so Confirmed may go straight to Processing; the cash is recorded
     *   when the order is Delivered.
     * - Mobile money is confirmed by the gateway only, never by hand.
     */
    public function nextStatuses(): array
    {
        $next = self::TRANSITIONS[$this->status] ?? [];

        if ($this->isCashOnDelivery() && $this->status === self::STATUS_CONFIRMED) {
            $next[] = self::STATUS_PROCESSING;
        }

        if ($this->usesMobileMoney()) {
            $next = array_diff($next, [self::STATUS_PAID]);
        }

        return array_values($next);
    }

    /**
     * Whether a manual transition from the current status is allowed.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }

    /**
     * Invoicing and shipping belong after payment (or, for cash on delivery,
     * after confirmation). Without this, core invoice/shipment actions moved
     * a Pending order straight to Processing and skipped the status flow.
     */
    protected function fulfilmentOpen(): bool
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_PROCESSING], true)) {
            return true;
        }

        return $this->isCashOnDelivery() && $this->status === self::STATUS_CONFIRMED;
    }

    public function canInvoice(): bool
    {
        return $this->fulfilmentOpen() && parent::canInvoice();
    }

    public function canShip(): bool
    {
        return $this->fulfilmentOpen() && parent::canShip();
    }

    /**
     * Customers may cancel only before payment/fulfilment. Once an order is
     * Paid or later, staff cancel it (which also flags the refund).
     * Staff (admin guard) and forced cancellations are unaffected.
     */
    public function canCancel(bool $force = false): bool
    {
        if (
            ! $force
            && ! auth('admin')->check()
            && ! in_array($this->status, [self::STATUS_PENDING, self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED], true)
        ) {
            return false;
        }

        return parent::canCancel($force);
    }
}
