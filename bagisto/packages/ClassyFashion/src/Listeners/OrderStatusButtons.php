<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Models\Sales\Order as ClassyOrder;
use Webkul\Theme\ViewRenderEventManager;

/**
 * Render allowed next-status buttons on the admin order view
 * (report 9.5/9.7 transitions) next to the status label.
 */
class OrderStatusButtons
{
    public function addButtons(ViewRenderEventManager $eventManager): void
    {
        $order = $eventManager->getParam('order');

        if (! $order) {
            return;
        }

        if (! $order instanceof ClassyOrder) {
            $order = ClassyOrder::find($order->id);
        }

        if (! $order) {
            return;
        }

        $next = ClassyOrder::TRANSITIONS[$order->status] ?? [];

        if (empty($next)) {
            return;
        }

        $html = '<div class="mt-2 flex flex-wrap gap-2">';

        foreach ($next as $status) {
            $label = (new ClassyOrder(['status' => $status]))->status_label;

            $html .= '<form method="POST" action="'.e(route('admin.classy.orders.status.update', $order->id)).'">'
                .csrf_field()
                .'<input type="hidden" name="status" value="'.e($status).'">'
                .'<button type="submit" class="secondary-button text-sm">'
                .e(__('classy-fashion::app.orders.mark_as', ['status' => $label]))
                .'</button></form>';
        }

        $html .= '</div>';

        $eventManager->addTemplate($html);
    }
}
