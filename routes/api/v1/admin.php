<?php

use Illuminate\Support\Facades\Route;
use NinjaPortal\Api\Http\Controllers\V1\Admin\ActivitiesController as AdminActivitiesController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\AdminsController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\ApiProductsController as AdminApiProductsController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\AudiencesController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\Auth\AuthController as AdminAuthController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\CategoriesController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\MeController as AdminMeController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\MenuItemsController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\MenusController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\Rbac\PermissionsController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\Rbac\RolesController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\SettingsController;
use NinjaPortal\Api\Http\Controllers\V1\Admin\UsersController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix(config('portal-api.admin_prefix', 'admin'))->group(function () {
    // Admin auth
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->name('portal-api.admin.auth.login');
    Route::post('/auth/refresh', [AdminAuthController::class, 'refresh'])->name('portal-api.admin.auth.refresh');

    $adminGuard = config('portal-api.auth.guards.admin', 'portal_api_admin');
    $rbacEnabled = (bool) config('portal-api.rbac.enabled', true);

    Route::middleware("auth:{$adminGuard}")->group(function () use ($adminGuard, $rbacEnabled) {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout'])->name('portal-api.admin.auth.logout');
        Route::get('/me', AdminMeController::class)->name('portal-api.admin.me');

        $mwManageAdmins = null;
        $mwManageRbac = null;
        $mwViewActivities = null;
        $adminAccessMiddleware = [];

        if ($rbacEnabled) {
            // Build RBAC middleware strings
            $bypassRoles = array_filter((array) config('portal-api.rbac.bypass_roles', []));
            $accessPermission = config('portal-api.rbac.permissions.access_admin', 'portal.admin.access');
            $manageRbacPermission = config('portal-api.rbac.permissions.manage_rbac', 'portal.rbac.manage');
            $manageAdminsPermission = config('portal-api.rbac.permissions.manage_admins', 'portal.admins.manage');
            $viewActivitiesPermission = config('portal-api.rbac.permissions.view_activities', 'portal.activities.view');

            $rbacAccess = implode('|', array_filter(array_merge($bypassRoles, [$accessPermission])));
            $adminAccessMiddleware = ["role_or_permission:{$rbacAccess},{$adminGuard}"];
            $mwManageAdmins = 'role_or_permission:'.implode('|', array_filter(array_merge($bypassRoles, [$manageAdminsPermission]))).",{$adminGuard}";
            $mwManageRbac = 'role_or_permission:'.implode('|', array_filter(array_merge($bypassRoles, [$manageRbacPermission]))).",{$adminGuard}";
            $mwViewActivities = 'role_or_permission:'.implode('|', array_filter(array_merge($bypassRoles, [$viewActivitiesPermission]))).",{$adminGuard}";
        }

        // Protected admin routes (requires admin access permission)
        Route::middleware($adminAccessMiddleware)->group(function () use ($rbacEnabled, $mwManageAdmins, $mwManageRbac, $mwViewActivities) {
            /*
            |--------------------------------------------------------------------------
            | Users/Developers Management
            |--------------------------------------------------------------------------
            */
            Route::apiResource('/users', UsersController::class)->names([
                'index' => 'portal-api.admin.users.index',
                'store' => 'portal-api.admin.users.store',
                'show' => 'portal-api.admin.users.show',
                'update' => 'portal-api.admin.users.update',
                'destroy' => 'portal-api.admin.users.destroy',
            ]);

            // User apps management
            Route::prefix('/users/{user}')->group(function () {
                Route::get('/apps', [UsersController::class, 'apps'])->name('portal-api.admin.users.apps.index');
                Route::post('/apps', [UsersController::class, 'createApp'])->name('portal-api.admin.users.apps.store');
                Route::put('/apps/{appName}', [UsersController::class, 'updateApp'])->name('portal-api.admin.users.apps.update');
                Route::delete('/apps/{appName}', [UsersController::class, 'deleteApp'])->name('portal-api.admin.users.apps.delete');
                Route::post('/apps/{appName}/approve', [UsersController::class, 'approveApp'])->name('portal-api.admin.users.apps.approve');
                Route::post('/apps/{appName}/revoke', [UsersController::class, 'revokeApp'])->name('portal-api.admin.users.apps.revoke');

                // App credentials
                Route::post('/apps/{appName}/credentials', [UsersController::class, 'createCredential'])->name('portal-api.admin.users.apps.credentials.create');
                Route::post('/apps/{appName}/credentials/{key}/approve', [UsersController::class, 'approveCredential'])->name('portal-api.admin.users.apps.credentials.approve');
                Route::post('/apps/{appName}/credentials/{key}/revoke', [UsersController::class, 'revokeCredential'])->name('portal-api.admin.users.apps.credentials.revoke');
                Route::delete('/apps/{appName}/credentials/{key}', [UsersController::class, 'deleteCredential'])->name('portal-api.admin.users.apps.credentials.delete');

                // Credential products
                Route::post('/apps/{appName}/credentials/{key}/products', [UsersController::class, 'addCredentialProducts'])->name('portal-api.admin.users.apps.credentials.products.add');
                Route::delete('/apps/{appName}/credentials/{key}/products/{product}', [UsersController::class, 'removeCredentialProduct'])->name('portal-api.admin.users.apps.credentials.products.remove');
                Route::post('/apps/{appName}/credentials/{key}/products/{product}/approve', [UsersController::class, 'approveCredentialProduct'])->name('portal-api.admin.users.apps.credentials.products.approve');
                Route::post('/apps/{appName}/credentials/{key}/products/{product}/revoke', [UsersController::class, 'revokeCredentialProduct'])->name('portal-api.admin.users.apps.credentials.products.revoke');
            });

            /*
            |--------------------------------------------------------------------------
            | Admin Accounts Management (requires special permission)
            |--------------------------------------------------------------------------
            */
            $adminsResource = Route::apiResource('/admins', AdminsController::class)
                ->parameters(['admins' => 'admin'])
                ->names([
                    'index' => 'portal-api.admin.admins.index',
                    'store' => 'portal-api.admin.admins.store',
                    'show' => 'portal-api.admin.admins.show',
                    'update' => 'portal-api.admin.admins.update',
                    'destroy' => 'portal-api.admin.admins.destroy',
                ]);
            if ($rbacEnabled && $mwManageAdmins) {
                $adminsResource->middleware($mwManageAdmins);
            }

            /*
            |--------------------------------------------------------------------------
            | API Products (Portal Catalog + Apigee Products)
            |--------------------------------------------------------------------------
            */
            Route::apiResource('/api-products', AdminApiProductsController::class)
                ->parameters(['api-products' => 'apiProduct'])
                ->names([
                    'index' => 'portal-api.admin.api-products.index',
                    'store' => 'portal-api.admin.api-products.store',
                    'show' => 'portal-api.admin.api-products.show',
                    'update' => 'portal-api.admin.api-products.update',
                    'destroy' => 'portal-api.admin.api-products.destroy',
                ]);
            Route::get('/apigee/api-products', [AdminApiProductsController::class, 'apigeeIndex'])->name('portal-api.admin.apigee.api-products.index');

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */
            Route::apiResource('/categories', CategoriesController::class)->names([
                'index' => 'portal-api.admin.categories.index',
                'store' => 'portal-api.admin.categories.store',
                'show' => 'portal-api.admin.categories.show',
                'update' => 'portal-api.admin.categories.update',
                'destroy' => 'portal-api.admin.categories.destroy',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Audiences
            |--------------------------------------------------------------------------
            */
            Route::apiResource('/audiences', AudiencesController::class)->names([
                'index' => 'portal-api.admin.audiences.index',
                'store' => 'portal-api.admin.audiences.store',
                'show' => 'portal-api.admin.audiences.show',
                'update' => 'portal-api.admin.audiences.update',
                'destroy' => 'portal-api.admin.audiences.destroy',
            ]);
            Route::post('/audiences/{audience}/users/sync', [AudiencesController::class, 'syncUsers'])
                ->name('portal-api.admin.audiences.users.sync');
            Route::post('/audiences/{audience}/products/sync', [AudiencesController::class, 'syncProducts'])
                ->name('portal-api.admin.audiences.products.sync');

            /*
            |--------------------------------------------------------------------------
            | Menus
            |--------------------------------------------------------------------------
            */
            Route::apiResource('/menus', MenusController::class)->names([
                'index' => 'portal-api.admin.menus.index',
                'store' => 'portal-api.admin.menus.store',
                'show' => 'portal-api.admin.menus.show',
                'update' => 'portal-api.admin.menus.update',
                'destroy' => 'portal-api.admin.menus.destroy',
            ]);
            Route::apiResource('/menus/{menu}/items', MenuItemsController::class)->names([
                'index' => 'portal-api.admin.menus.items.index',
                'store' => 'portal-api.admin.menus.items.store',
                'show' => 'portal-api.admin.menus.items.show',
                'update' => 'portal-api.admin.menus.items.update',
                'destroy' => 'portal-api.admin.menus.items.destroy',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Settings
            |--------------------------------------------------------------------------
            */
            Route::apiResource('/settings', SettingsController::class)->names([
                'index' => 'portal-api.admin.settings.index',
                'store' => 'portal-api.admin.settings.store',
                'show' => 'portal-api.admin.settings.show',
                'update' => 'portal-api.admin.settings.update',
                'destroy' => 'portal-api.admin.settings.destroy',
            ]);

            /*
            |--------------------------------------------------------------------------
            | RBAC (requires special permission)
            |--------------------------------------------------------------------------
            */
            $rolesResource = Route::apiResource('/roles', RolesController::class)->names([
                'index' => 'portal-api.admin.roles.index',
                'store' => 'portal-api.admin.roles.store',
                'show' => 'portal-api.admin.roles.show',
                'update' => 'portal-api.admin.roles.update',
                'destroy' => 'portal-api.admin.roles.destroy',
            ]);
            if ($rbacEnabled && $mwManageRbac) {
                $rolesResource->middleware($mwManageRbac);
            }
            $rolesSyncPermissions = Route::post('/roles/{role}/permissions/sync', [RolesController::class, 'syncPermissions'])
                ->name('portal-api.admin.roles.permissions.sync');
            if ($rbacEnabled && $mwManageRbac) {
                $rolesSyncPermissions->middleware($mwManageRbac);
            }

            $permissionsResource = Route::apiResource('/permissions', PermissionsController::class)->names([
                'index' => 'portal-api.admin.permissions.index',
                'store' => 'portal-api.admin.permissions.store',
                'show' => 'portal-api.admin.permissions.show',
                'update' => 'portal-api.admin.permissions.update',
                'destroy' => 'portal-api.admin.permissions.destroy',
            ]);
            if ($rbacEnabled && $mwManageRbac) {
                $permissionsResource->middleware($mwManageRbac);
            }

            /*
            |--------------------------------------------------------------------------
            | Activities
            |--------------------------------------------------------------------------
            */
            $activitiesIndex = Route::get('/activities', [AdminActivitiesController::class, 'index'])
                ->name('portal-api.admin.activities.index');
            $activitiesExport = Route::get('/activities/export', [AdminActivitiesController::class, 'export'])
                ->name('portal-api.admin.activities.export');
            $activitiesShow = Route::get('/activities/{activity}', [AdminActivitiesController::class, 'show'])
                ->name('portal-api.admin.activities.show');
            if ($rbacEnabled && $mwViewActivities) {
                $activitiesIndex->middleware($mwViewActivities);
                $activitiesExport->middleware($mwViewActivities);
                $activitiesShow->middleware($mwViewActivities);
            }
        });
    });
});
