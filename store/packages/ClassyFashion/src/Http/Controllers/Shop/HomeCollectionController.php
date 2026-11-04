<?php

namespace ClassyFashion\Http\Controllers\Shop;

use ClassyFashion\Repositories\HomeCollectionsRepository;
use Illuminate\Http\JsonResponse;
use Webkul\Shop\Helpers\CatalogApiCache;
use Webkul\Shop\Http\Resources\ProductCardResource;

/**
 * Serve the distinct collections in the existing Ethereal card format.
 */
class HomeCollectionController
{
    /**
     * Retain customer-specific wishlist behaviour through the catalogue cache helper.
     */
    public function index(string $collection, HomeCollectionsRepository $collections, CatalogApiCache $cache): JsonResponse
    {
        abort_unless(in_array($collection, ['arrivals', 'layers', 'everyday', 'finishing'], true), 404);

        $data = $cache->remember('classy-home', ['collection' => $collection], fn () => ProductCardResource::collection($collections->products($collection))->response()->getData(true));

        return response()->json($data);
    }
}
