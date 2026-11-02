<?php

namespace ClassyFashion\Repositories;

use Illuminate\Support\Collection;
use Webkul\Product\Models\Product;
use Webkul\Sales\Models\Order;

/**
 * Keep assistant catalogue matching consistent across MySQL and PostgreSQL.
 */
class AssistantCatalogRepository
{
    /**
     * Limit order tracking to the authenticated customer's own records.
     */
    public function latestOrder(int $customerId): ?Order
    {
        return Order::where('customer_id', $customerId)->latest('id')->first();
    }

    /**
     * Count parent styles rather than every variant.
     */
    public function styleCount(): int
    {
        return Product::whereNull('parent_id')->count();
    }

    /**
     * Match colours, categories, names, and UGX ceilings; null means no search intent.
     */
    public function search(string $text, int $limit): ?Collection
    {
        $query = Product::query()
            ->whereNull('products.parent_id')
            ->join('product_flat', function ($join) {
                $join->on('product_flat.product_id', '=', 'products.id')
                    ->where('product_flat.locale', 'en')
                    ->where('product_flat.channel', 'default');
            })
            ->where('product_flat.status', 1)
            ->select('products.*');

        $matched = false;

        $colors = \DB::table('attribute_options')
            ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')
            ->where('attributes.code', 'color')
            ->pluck('attribute_options.admin_name')
            ->map(fn ($n) => mb_strtolower($n))
            ->all();

        foreach ($colors as $color) {
            if ($color !== '' && str_contains($text, $color)) {
                $query->whereExists(function ($q) use ($color) {
                    $q->selectRaw(1)->from('products as variants')
                        ->join('product_attribute_values as pav', 'pav.product_id', '=', 'variants.id')
                        ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
                        ->join('attribute_options as ao', 'ao.id', '=', 'pav.integer_value')
                        ->whereColumn('variants.parent_id', 'products.id')
                        ->where('a.code', 'color')
                        ->whereRaw('LOWER(ao.admin_name) = ?', [$color]);
                });

                $matched = true;

                break;
            }
        }

        $categories = ['mens' => 2, 'womens' => 4, 'women' => 4, 'men' => 2, 'footwear' => 8, 'shoes' => 8, 'casual' => 6];

        foreach ($categories as $word => $categoryId) {
            if (str_contains($text, $word)) {
                $query->join('product_categories', 'product_categories.product_id', '=', 'products.id')
                    ->where('product_categories.category_id', $categoryId);

                $matched = true;

                break;
            }
        }

        foreach (['shirt', 'jacket', 'dress', 'sneaker', 'loafer', 'belt', 'scarf', 'bag', 'cap', 'beanie', 'sock', 'gomesi', 'kitenge', 'ankara'] as $word) {
            if (str_contains($text, $word)) {
                $query->whereLike('product_flat.name', '%'.$word.'%');

                $matched = true;

                break;
            }
        }

        if (preg_match('/(?:under|below|max|less than)\s*([\d\s,]+)k?\b/', $text, $m)) {
            $ceiling = (int) preg_replace('/\D/', '', $m[1]);

            if (str_ends_with(trim($m[1]), 'k')) {
                $ceiling *= 1000;
            }

            if ($ceiling > 0) {
                $query->where('product_flat.price', '<=', $ceiling);

                $matched = true;
            }
        }

        if (! $matched) {
            return null;
        }

        return $query->orderBy('product_flat.price')->limit($limit)->get();
    }
}
