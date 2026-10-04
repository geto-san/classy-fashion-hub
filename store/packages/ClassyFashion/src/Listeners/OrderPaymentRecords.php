<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Models\ManualPayment;
use ClassyFashion\Models\PaymentAttempt;
use Webkul\Theme\ViewRenderEventManager;

/**
 * Show how an order was paid on the admin order view (report 9.5/9.6):
 * the verified mobile-money transaction, or who took the cash and when.
 */
class OrderPaymentRecords
{
    public function show(ViewRenderEventManager $eventManager): void
    {
        $order = $eventManager->getParam('order');

        if (! $order) {
            return;
        }

        $lines = [];

        $attempt = PaymentAttempt::where('order_id', $order->id)->first();

        if ($attempt) {
            $lines[] = "Mobile money {$attempt->network}: {$attempt->tx_ref} ({$attempt->status})";
        }

        foreach (ManualPayment::where('order_id', $order->id)->with('receiver')->get() as $payment) {
            $lines[] = core()->formatBasePrice($payment->amount).' received ('.$payment->method.') by '
                .($payment->receiver?->name ?? 'unknown').' on '.$payment->received_at->format('d M Y H:i');
        }

        if ($lines === []) {
            return;
        }

        $html = '<div class="mt-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-gray-800">'
            .'<p class="font-semibold text-gray-600 dark:text-gray-300">Payment record</p>';

        foreach ($lines as $line) {
            $html .= '<p class="mt-1 text-gray-800 dark:text-white">'.e($line).'</p>';
        }

        $eventManager->addTemplate($html.'</div>');
    }
}
