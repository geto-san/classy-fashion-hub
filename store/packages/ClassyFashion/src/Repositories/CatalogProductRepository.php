<?php

namespace ClassyFashion\Repositories;

use Webkul\Product\Repositories\ProductRepository;

/**
 * Keep page boundaries stable when product names, prices or dates tie.
 */
class CatalogProductRepository extends ProductRepository
{
    /**
     * Preserve the requested sort while making its final order deterministic.
     */
    public function paginate($limit = null, $columns = ['*'], $method = 'paginate')
    {
        $scope = $this->scopeQuery;
        $this->scopeQuery = fn ($query) => ($scope ? $scope($query) : $query)->orderBy('products.id');

        try {
            return parent::paginate($limit, $columns, $method);
        } finally {
            $this->scopeQuery = $scope;
        }
    }
}
