<?php

use Illuminate\Support\Facades\Route;
use NinjaPortal\Api\Http\Controllers\V1\User\AppsController;
use NinjaPortal\Api\Http\Controllers\V1\User\Auth\AuthController as UserAuthController;
use NinjaPortal\Api\Http\Controllers\V1\User\MeController as UserMeController;

/*
|--------------------------------------------------------------------------
| Consumer Auth & Profile (Developer/User Facing)
|--------------------------------------------------------------------------
*/
Route::post('/auth/register', [UserAuthController::class, 'register'])->name('portal-api.auth.register');
Route::post('/auth/login', [UserAuthController::class, 'login'])->name('portal-api.auth.login');
Route::post('/auth/refresh', [UserAuthController::class, 'refresh'])->name('portal-api.auth.refresh');
Route::post('/auth/forgot-password', [UserAuthController::class, 'forgotPassword'])->name('portal-api.auth.forgot');
Route::post('/auth/reset-password', [UserAuthController::class, 'resetPassword'])->name('portal-api.auth.reset');

Route::middleware('auth:'.config('portal-api.auth.guards.consumer', 'api'))->group(function () {
    Route::post('/auth/logout', [UserAuthController::class, 'logout'])->name('portal-api.auth.logout');
    Route::get('/me', UserMeController::class)->name('portal-api.me');
    Route::put('/me', [UserMeController::class, 'update'])->name('portal-api.me.update');
    Route::post('/me/password', [UserMeController::class, 'updatePassword'])->name('portal-api.me.password');

    // Consumer apps management
    Route::apiResource('/me/apps', AppsController::class)
        ->parameters(['apps' => 'appName'])
        ->names([
            'index' => 'portal-api.me.apps.index',
            'store' => 'portal-api.me.apps.store',
            'show' => 'portal-api.me.apps.show',
            'update' => 'portal-api.me.apps.update',
            'destroy' => 'portal-api.me.apps.destroy',
        ]);

    Route::prefix('/me/apps/{appName}')->group(function () {
        Route::post('/approve', [AppsController::class, 'approve'])->name('portal-api.me.apps.approve');
        Route::post('/revoke', [AppsController::class, 'revoke'])->name('portal-api.me.apps.revoke');

        // Credentials management
        Route::post('/credentials', [AppsController::class, 'createCredential'])->name('portal-api.me.apps.credentials.create');
        Route::post('/credentials/{key}/approve', [AppsController::class, 'approveCredential'])->name('portal-api.me.apps.credentials.approve');
        Route::post('/credentials/{key}/revoke', [AppsController::class, 'revokeCredential'])->name('portal-api.me.apps.credentials.revoke');
        Route::delete('/credentials/{key}', [AppsController::class, 'deleteCredential'])->name('portal-api.me.apps.credentials.delete');

        // Credential products management
        Route::post('/credentials/{key}/products', [AppsController::class, 'addCredentialProducts'])->name('portal-api.me.apps.credentials.products.add');
        Route::delete('/credentials/{key}/products/{product}', [AppsController::class, 'removeCredentialProduct'])->name('portal-api.me.apps.credentials.products.remove');
        Route::post('/credentials/{key}/products/{product}/approve', [AppsController::class, 'approveCredentialProduct'])->name('portal-api.me.apps.credentials.products.approve');
        Route::post('/credentials/{key}/products/{product}/revoke', [AppsController::class, 'revokeCredentialProduct'])->name('portal-api.me.apps.credentials.products.revoke');
    });
});
