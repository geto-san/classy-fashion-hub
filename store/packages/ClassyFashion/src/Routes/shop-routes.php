<?php

use ClassyFashion\Http\Controllers\Shop\AssistantController;
use ClassyFashion\Http\Controllers\Shop\HomeCollectionController;
use ClassyFashion\Http\Controllers\Shop\MobileMoneyController;
use Illuminate\Support\Facades\Route;

/**
 * Classy Fashion Hub storefront routes.
 */
AssistantController::routes();
Route::middleware('web')->get('classy/collections/{collection}', [HomeCollectionController::class, 'index'])->name('classy.collections.index');
MobileMoneyController::routes();
