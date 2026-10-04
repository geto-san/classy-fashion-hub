<?php

namespace ClassyFashion\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;
use Webkul\Sales\Models\Order;

/**
 * Plain-text e-mails for the moments the report cares about (9.8):
 * order status changes (customer) and low stock (owner).
 *
 * Mail trouble must never break a sale or a status change, so every send is
 * wrapped; failures are reported to the log instead. With MAIL_MAILER=log
 * (the default until SMTP is configured) the mail is written to the log.
 */
class Notify
{
    protected const ORDER_MESSAGES = [
        'confirmed'  => 'We have confirmed your order and will prepare it shortly.',
        'paid'       => 'We have received your payment. Thank you!',
        'processing' => 'We are packing your order.',
        'dispatched' => 'Your order is on its way to you.',
        'delivered'  => 'Your order has been delivered. We hope you love it!',
        'canceled'   => 'Your order has been canceled. If you were charged, we will refund you.',
    ];

    public static function orderStatus(Order $order, string $status): void
    {
        if (! isset(self::ORDER_MESSAGES[$status]) || blank($order->customer_email)) {
            return;
        }

        $shop = config('app.name', 'Classy Fashion Hub');

        self::send(
            $order->customer_email,
            "{$shop}: order #{$order->increment_id} is ".$status,
            "Hello {$order->customer_first_name},\n\n"
            .self::ORDER_MESSAGES[$status]."\n\n"
            ."Order: #{$order->increment_id}\n"
            .'Total: '.core()->formatBasePrice($order->base_grand_total)."\n\n"
            ."Questions? Reply to this e-mail or contact us at the shop.\n\n{$shop}"
        );
    }

    public static function lowStock(?string $sku, int $quantity, int $threshold): void
    {
        $to = config('mail.admin.address');

        if (blank($to)) {
            return;
        }

        $shop = config('app.name', 'Classy Fashion Hub');

        self::send(
            $to,
            "{$shop}: low stock for {$sku}",
            "{$sku} is down to {$quantity} (alert level {$threshold}). Time to restock.\n\n{$shop}"
        );
    }

    protected static function send(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, fn ($message) => $message->to($to)->subject($subject));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
