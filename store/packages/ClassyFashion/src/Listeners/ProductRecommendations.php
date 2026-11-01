<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Support\Recommender;
use Webkul\Theme\ViewRenderEventManager;

/**
 * Render the existing product recommendations in the Ethereal product composition.
 */
class ProductRecommendations
{
    /**
     * Keep the recommendation ranking while using the storefront product presentation.
     */
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

        $eventManager->addTemplate('classy-fashion::products.recommendations', ['picks' => $picks]);
    }
}
