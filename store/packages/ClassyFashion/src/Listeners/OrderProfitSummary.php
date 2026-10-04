<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Support\Profit;
use Webkul\Sales\Models\Order as WebkulOrder;
use Webkul\Theme\ViewRenderEventManager;

/**
 * Show profit per order item and per order on the admin order view
 * (report 9.4).
 */
class OrderProfitSummary
{
    public function showItemProfit(ViewRenderEventManager $eventManager): void
    {
        $item = $eventManager->getParam('item');

        if (! $item || ! bouncer()->hasPermission('reporting.profit')) {
            return;
        }

        $profit = Profit::itemProfit($item);

        $html = '<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">'
            .e(__('classy-fashion::app.orders.profit', ['amount' => core()->formatBasePrice($profit)]))
            .'</p>';

        $eventManager->addTemplate($html);
    }

    public function showOrderProfit(ViewRenderEventManager $eventManager): void
    {
        // Cost and profit are for the owner: staff without the profit
        // permission (e.g. Worker) never see them.
        if (! bouncer()->hasPermission('reporting.profit')) {
            return;
        }

        // This view event carries no params; resolve the order from the route.
        $order = $eventManager->getParam('order')
            ?? WebkulOrder::query()->find(request()->route('id'));

        if (! $order) {
            return;
        }

        $html = '<div class="flex justify-between gap-y-1">'
            .'<p class="text-[15px] text-gray-600 dark:text-gray-300">'
            .e(__('classy-fashion::app.orders.order_profit'))
            .'</p>'
            .'<p class="text-[15px] font-semibold text-emerald-600">'
            .e(core()->formatBasePrice(Profit::orderProfit($order)))
            .'</p></div>';

        $eventManager->addTemplate($html);
    }
}
