<?php

use ClassyFashion\Http\Controllers\Admin\AuditLogController;
use ClassyFashion\Http\Controllers\Admin\OrderStatusController;
use ClassyFashion\Http\Controllers\Admin\PaymentAttemptController;
use ClassyFashion\Http\Controllers\Admin\ProfitReportController;
use Illuminate\Support\Facades\Route;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

/**
 * Classy Fashion Hub admin routes (report customizations).
 */
Route::group(['middleware' => ['web', 'admin', NoCacheMiddleware::class], 'prefix' => config('app.admin_url')], function () {
    Route::post('classy/orders/{id}/status', [OrderStatusController::class, 'update'])
        ->name('admin.classy.orders.status.update');

    Route::post('classy/orders/{id}/partner', [OrderStatusController::class, 'partner'])
        ->name('admin.classy.orders.partner.update');

    Route::get('classy/reports/profit', [ProfitReportController::class, 'index'])
        ->name('admin.classy.reports.profit');

    Route::get('classy/reports/audit', [AuditLogController::class, 'index'])
        ->name('admin.classy.reports.audit');

    Route::get('classy/payments', [PaymentAttemptController::class, 'index'])
        ->name('admin.classy.payments.index');
});
