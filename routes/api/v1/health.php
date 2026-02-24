<?php

use Illuminate\Support\Facades\Route;
use NinjaPortal\Api\Http\Controllers\V1\Public\HealthController;

Route::get('/health', HealthController::class)->name('portal-api.health');
