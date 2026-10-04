<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Models\Sales\Order as ClassyOrder;
use Spatie\Activitylog\Models\Activity;
use Throwable;
use Webkul\Sales\Models\Order;
use Webkul\Theme\ViewRenderEventManager;

/**
 * Customer-visible order history (report 9.5/9.8): each status the order
 * reached and when, on the customer's order page.
 */
class OrderTimeline
{
    public function show(ViewRenderEventManager $eventManager): void
    {
        $order = $eventManager->getParam('order');

        if (! $order) {
            return;
        }

        $steps = [[__('classy-fashion::app.timeline.placed'), $order->created_at]];

        $entries = Activity::query()
            ->where('log_name', 'classy-fashion')
            ->whereIn('subject_type', [Order::class, ClassyOrder::class])
            ->where('subject_id', $order->id)
            ->whereIn('event', ['order.status', 'order.payment'])
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $to = $entry->getExtraProperty('to');

            if ($to) {
                $steps[] = [$this->label((string) $to), $entry->created_at];
            }
        }

        if (count($steps) < 2) {
            return;
        }

        $html = '<div class="mt-6 rounded-xl border p-4 max-md:mt-4">'
            .'<p class="text-lg font-medium">'.e(__('classy-fashion::app.timeline.title')).'</p>'
            .'<ol class="mt-3 flex flex-col gap-2 text-sm">';

        foreach ($steps as [$label, $when]) {
            $html .= '<li class="flex justify-between gap-4"><span class="font-medium">'.e($label).'</span>'
                .'<span class="text-zinc-500">'.e($when->format('d M Y H:i')).'</span></li>';
        }

        $eventManager->addTemplate($html.'</ol></div>');
    }

    protected function label(string $status): string
    {
        try {
            return (new ClassyOrder(['status' => $status]))->status_label;
        } catch (Throwable $e) {
            return ucfirst($status);
        }
    }
}
