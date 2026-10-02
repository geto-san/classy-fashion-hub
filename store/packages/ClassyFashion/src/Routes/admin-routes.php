<?php

use ClassyFashion\Http\Controllers\Admin\OrderStatusController;
use ClassyFashion\Http\Controllers\Admin\ProfitReportController;
use Illuminate\Support\Facades\Route;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

/**
 * Classy Fashion Hub admin routes (report customizations).
 */
Route::group(['middleware' => ['web', 'admin', NoCacheMiddleware::class], 'prefix' => config('app.admin_url')], function () {
    Route::post('classy/orders/{id}/status', [OrderStatusController::class, 'update'])
        ->name('admin.classy.orders.status.update');

    Route::get('classy/reports/profit', [ProfitReportController::class, 'index'])
        ->name('admin.classy.reports.profit');
});
