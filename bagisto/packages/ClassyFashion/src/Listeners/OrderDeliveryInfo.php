<?php

namespace ClassyFashion\Listeners;

use Webkul\Theme\ViewRenderEventManager;

/**
 * Show saved delivery instructions on the admin order view
 * (report 9.7) beneath the address they belong to.
 */
class OrderDeliveryInfo
{
    public function showBillingInfo(ViewRenderEventManager $eventManager): void
    {
        $this->render($eventManager, 'billing_address');
    }

    public function showShippingInfo(ViewRenderEventManager $eventManager): void
    {
        $this->render($eventManager, 'shipping_address');
    }

    protected function render(ViewRenderEventManager $eventManager, string $relation): void
    {
        $order = $eventManager->getParam('order');

        $instructions = $order?->{$relation}?->delivery_instructions;

        if (! $instructions) {
            return;
        }

        $html = '<div class="mt-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-gray-800">'
            .'<p class="font-semibold text-gray-600 dark:text-gray-300">'
            .e(__('classy-fashion::app.orders.delivery_instructions'))
            .'</p>'
            .'<p class="mt-1 text-gray-800 dark:text-white">'.e($instructions).'</p>'
            .'</div>';

        $eventManager->addTemplate($html);
    }
}
