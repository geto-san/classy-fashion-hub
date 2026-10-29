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

        $html = '<section class="cfh-section cfh-picks" aria-label="You may also like">'
            .'<header class="cfh-section__head"><div><p class="cfh-eyebrow">Recommended</p>'
            .'<h2 class="cfh-title">You may also like</h2></div></header>'
            .'<div class="cfh-picks__grid">';

        foreach ($picks as $pick) {
            $image = $pick->images->first()?->url ?? '';
            $url = url($pick->url_key);
            $html .= '<a href="'.e($url).'" class="cfh-pick">'
                .'<span class="cfh-pick__img">'
                .($image ? '<img src="'.e($image).'" alt="'.e($pick->name).'" loading="lazy">' : '')
                .'</span>'
                .'<span class="cfh-pick__name">'.e($pick->name).'</span>'
                .'<span class="cfh-pick__price">'.e(core()->formatPrice((float) $pick->price)).'</span>'
                .'</a>';
        }

        $html .= '</div></section>';

        $eventManager->addTemplate($html);
    }
}
