<?php

namespace ClassyFashion\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Sales\Models\Order;
use Webkul\User\Models\Admin;

/**
 * A payment confirmed by a person rather than by the gateway.
 */
class ManualPayment extends Model
{
    protected $table = 'classy_manual_payments';

    protected $fillable = ['order_id', 'method', 'amount', 'received_by', 'received_at', 'note'];

    protected $casts = ['received_at' => 'datetime'];

    public function receiver()
    {
        return $this->belongsTo(Admin::class, 'received_by');
    }

    /**
     * Record once per order and method; returns the existing row on repeats.
     */
    public static function record(Order $order, string $method, ?Admin $by = null, ?string $note = null): self
    {
        return static::firstOrCreate(
            ['order_id' => $order->id, 'method' => $method],
            [
                'amount'      => $order->base_grand_total,
                'received_by' => $by?->id,
                'received_at' => now(),
                'note'        => $note,
            ]
        );
    }
}
