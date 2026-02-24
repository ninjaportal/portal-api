<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal API Routes
|--------------------------------------------------------------------------
|
| Version 1 API routes for NinjaPortal.
| Base path: {prefix}/api/v1 (configurable via portal-api.prefix)
|
*/

Route::prefix(config('portal-api.prefix', 'api/v1'))
    ->middleware(config('portal-api.middleware', ['api']))
    ->group(function () {
        require __DIR__.'/api/v1/health.php';
        require __DIR__.'/api/v1/consumer.php';
        require __DIR__.'/api/v1/public.php';
        require __DIR__.'/api/v1/admin.php';
    });
