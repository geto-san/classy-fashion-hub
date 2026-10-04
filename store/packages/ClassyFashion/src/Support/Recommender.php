<?php

namespace ClassyFashion\Support;

use Illuminate\Support\Collection;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\OrderItem;

/**
 * Product recommendations (report 5.1, first stage).
 *
 * Rule-based engine that always works offline:
 *  1. Frequently bought together (order co-occurrence).
 *  2. Same-category similarity (shared category + in stock).
 * An LLM re-rank can be layered on top when keys exist; the base
 * ordering is always deterministic and testable.
 */
class Recommender
{
    /**
     * Products frequently bought together with the given product.
     */
    public static function boughtTogether(int $productId, int $limit = 4): Collection
    {
        $orderIds = OrderItem::query()
            ->where('product_id', $productId)
            ->distinct()
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $ids = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->where('product_id', '!=', $productId)
            ->whereNull('parent_id')
            ->selectRaw('product_id, COUNT(*) as times')
            ->groupBy('product_id')
            ->orderByDesc('times')
            ->limit($limit)
            ->pluck('product_id');

        return self::sellable($ids);
    }

    /**
     * Similar in-stock products sharing a category, cheapest first.
     */
    public static function similar(Product $product, int $limit = 4): Collection
    {
        $categoryIds = $product->categories()->pluck('categories.id');

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $ids = Product::query()
            ->where('products.id', '!=', $product->id)
            ->whereNull('products.parent_id')
            ->join('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.locale', 'en')
                    ->where('product_flat.channel', 'default');
            })
            ->where('product_flat.status', 1)
            ->join('product_categories', 'product_categories.product_id', '=', 'products.id')
            ->whereIn('product_categories.category_id', $categoryIds)
            ->distinct()
            ->pluck('products.id');

        return self::sellable($ids)->take($limit);
    }

    /**
     * Combined picks for a product page: bought-together first,
     * topped up with similar items. Never empty when the catalog isn't.
     */
    public static function picksFor(Product $product, int $limit = 4): Collection
    {
        $picks = self::boughtTogether($product->id, $limit);

        if ($picks->count() < $limit) {
            $similar = self::similar($product, $limit);

            $picks = $picks->merge($similar->reject(fn ($p) => $picks->contains('id', $p->id)))
                ->take($limit);
        }

        return $picks->values();
    }

    /**
     * Keep only visible, in-stock parents with a price, cheapest first.
     */
    protected static function sellable(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->whereIn('products.id', $ids->all())
            ->whereNull('products.parent_id')
            ->join('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.locale', 'en')
                    ->where('product_flat.channel', 'default');
            })
            ->where('product_flat.status', 1)
            ->orderBy('product_flat.price')
            ->select('products.*')
            ->with(['images'])
            ->get()
            ->filter(fn (Product $p) => (float) $p->price > 0 && $p->haveSufficientQuantity(1));
    }
}
