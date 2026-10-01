<?php

use ClassyFashion\Http\Controllers\Admin\OrderStatusController;
use Illuminate\Support\Facades\Route;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

/**
 * Classy Fashion Hub admin routes (report customizations).
 */
Route::group(['middleware' => ['admin', NoCacheMiddleware::class], 'prefix' => config('app.admin_url')], function () {
    Route::post('classy/orders/{id}/status', [OrderStatusController::class, 'update'])
        ->name('admin.classy.orders.status.update');
});
