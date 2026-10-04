<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Support\Recommender;
use Webkul\Theme\ViewRenderEventManager;

/**
 * "You may also like" on product pages (report 5.1).
 */
class ProductRecommendations
{
    public function showPicks(ViewRenderEventManager $eventManager): void
    {
        $product = $eventManager->getParam('product');

        if (! $product) {
            return;
        }

        $picks = Recommender::picksFor($product, 4);

        if ($picks->isEmpty()) {
            return;
        }

        $html = '<div class="container mt-14 max-lg:px-8 max-md:mt-7">'
            .'<h2 class="font-dmserif text-3xl max-md:text-2xl max-sm:text-xl">You may also like</h2>'
            .'<div class="mt-4 grid grid-cols-4 gap-4 max-md:grid-cols-2">';

        foreach ($picks as $pick) {
            $image = $pick->images->first()?->url ?? '';
            $url = url($pick->url_key);
            $html .= '<a href="'.e($url).'" class="overflow-hidden rounded-lg border border-zinc-200 bg-white transition hover:shadow-md">'
                .($image ? '<img src="'.e($image).'" alt="'.e($pick->name).'" class="h-48 w-full object-cover" loading="lazy">' : '')
                .'<div class="p-3"><p class="text-base font-medium max-sm:text-sm">'.e($pick->name).'</p>'
                .'<p class="mt-1 text-lg font-semibold max-sm:text-base">'.e(core()->formatPrice((float) $pick->price)).'</p></div></a>';
        }

        $html .= '</div></div>';

        $eventManager->addTemplate($html);
    }
}
