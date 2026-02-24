<?php

use Illuminate\Support\Facades\Route;
use NinjaPortal\Api\Http\Controllers\V1\Public\ApiProductsController as PublicApiProductsController;
use NinjaPortal\Api\Http\Controllers\V1\Public\ConfigController;

/*
|--------------------------------------------------------------------------
| Public API Products (Catalog)
|--------------------------------------------------------------------------
*/
Route::get('/api-products', [PublicApiProductsController::class, 'index'])->name('portal-api.api-products.index');
Route::get('/api-products/{apiProduct}', [PublicApiProductsController::class, 'show'])->name('portal-api.api-products.show');

/*
|--------------------------------------------------------------------------
| Public Config
|--------------------------------------------------------------------------
*/
Route::get('/config', ConfigController::class)->name('portal-api.config');
