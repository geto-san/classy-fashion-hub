<?php

namespace ClassyFashion\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Product\Repositories\ProductInventoryRepository;

/**
 * Quantity-only inventory access without supplier costs.
 */
class StockController extends Controller
{
    public function index(ProductInventoryRepository $inventories)
    {
        abort_unless(bouncer()->hasPermission('catalog.stock'), 401);

        return view('classy-fashion::admin.stock', [
            'inventories' => $inventories->with('product')->paginate(30),
        ]);
    }

    public function update(Request $request, int $id, ProductInventoryRepository $inventories)
    {
        abort_unless(bouncer()->hasPermission('catalog.stock'), 401);

        $data = $request->validate(['qty' => ['required', 'integer', 'min:0']]);
        $inventories->findOrFail($id);
        $inventories->update($data, $id);

        return redirect()->route('admin.classy.stock.index');
    }
}
