<?php

namespace ClassyFashion\Repositories;

use Illuminate\Support\Facades\DB;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Product\Repositories\ProductRepository;

/**
 * Allocate different products to each Ethereal homepage collection.
 */
class HomeCollectionsRepository
{
    /**
     * Use the published product index and ordinary category membership.
     */
    public function __construct(protected ProductRepository $products, protected CategoryRepository $categories) {}

    /**
     * Return a deterministic selection without repeating another homepage section.
     */
    public function products(string $collection): mixed
    {
        $ids = DB::table('product_flat')->join('products', 'products.id', '=', 'product_flat.product_id')
            ->where('product_flat.channel', core()->getCurrentChannel()->code)
            ->where('product_flat.locale', core()->getCurrentLocale()->code)
            ->where('product_flat.status', 1)->where('product_flat.visible_individually', 1)
            ->whereNull('products.parent_id')->orderByDesc('products.created_at')->orderByDesc('products.id')
            ->pluck('products.id')->unique()->values()->all();
        $membership = DB::table('product_categories')->whereIn('product_id', $ids)->get()->groupBy('product_id');
        $departments = [];

        foreach (['women', 'men', 'outerwear', 'footwear', 'accessories'] as $slug) {
            $departments[$slug] = $this->categories->findBySlug($slug)?->id;
        }

        $selected = [];
        $groups = [];

        foreach (['arrivals' => [null, 12], 'layers' => [['footwear', 'outerwear'], 8], 'everyday' => [['women', 'men'], 12], 'finishing' => [['accessories'], 8]] as $key => [$slugs, $limit]) {
            $categoryIds = $slugs ? array_filter(array_map(fn ($slug) => $departments[$slug], $slugs)) : [];
            $available = array_values(array_filter($ids, function ($id) use ($selected, $slugs, $categoryIds, $membership) {
                return ! in_array($id, $selected, true) && (! $slugs || collect($membership[$id] ?? [])->contains(fn ($row) => in_array($row->category_id, $categoryIds)));
            }));
            $groups[$key] = array_slice($available, 0, $limit);
            $selected = array_merge($selected, $groups[$key]);
        }

        $wanted = $groups[$collection] ?? [];
        $products = $this->products->getModel()->with(['images', 'attribute_values'])->whereIn('id', $wanted)->get()->keyBy('id');

        return collect($wanted)->map(fn ($id) => $products[$id])->values();
    }
}
