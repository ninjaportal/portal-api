<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminGuard = (string) config('portal-api.auth.guards.admin', 'portal_api_admin');
        if (trim($adminGuard) === '') {
            return;
        }

        $permissions = [
            (string) (config('portal-api.rbac.permissions.access_admin') ?? 'portal.admin.access'),
            (string) (config('portal-api.rbac.permissions.manage_rbac') ?? 'portal.rbac.manage'),
            (string) (config('portal-api.rbac.permissions.manage_admins') ?? 'portal.admins.manage'),
            (string) (config('portal-api.rbac.permissions.view_activities') ?? 'portal.activities.view'),
        ];

        $permissions = array_values(array_unique(array_filter($permissions, fn ($p) => is_string($p) && trim($p) !== '')));

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => $adminGuard],
                ['name' => $permission, 'guard_name' => $adminGuard],
            );
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => $adminGuard],
            ['name' => 'super_admin', 'guard_name' => $adminGuard],
        );

        $role->syncPermissions($permissions);

        $adminModel = (string) config('portal-api.auth.admin_model');
        if (trim($adminModel) === '' || ! class_exists($adminModel)) {
            return;
        }

        $webSuperAdminRoleId = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($webSuperAdminRoleId) {
            $adminIds = DB::table('model_has_roles')
                ->where('role_id', $webSuperAdminRoleId)
                ->where('model_type', $adminModel)
                ->pluck('model_id')
                ->all();

            foreach ($adminIds as $adminId) {
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => $role->id,
                    'model_type' => $adminModel,
                    'model_id' => $adminId,
                ], [
                    'role_id' => $role->id,
                    'model_type' => $adminModel,
                    'model_id' => $adminId,
                ]);
            }

            return;
        }

        $firstAdmin = $adminModel::query()->orderBy('id')->first();
        if ($firstAdmin && method_exists($firstAdmin, 'assignRole')) {
            $firstAdmin->assignRole($role);
        }
    }
};

