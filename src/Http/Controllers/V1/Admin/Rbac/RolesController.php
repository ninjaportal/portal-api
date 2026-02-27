<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin\Rbac;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\Rbac\RoleResource;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreRoleRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateRoleRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\SyncRolePermissionsRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @group Admin: RBAC
 */
class RolesController extends Controller
{
    /**
     * List roles
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\Rbac\RoleResource
     * @apiResourceModel Spatie\Permission\Models\Role with=permissions
     */
    public function index(Request $request)
    {
        $guard = (string) config('portal-api.auth.guards.admin', 'admin');
        $roles = Role::query()
            ->where('guard_name', $guard)
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return response()->success(RoleResource::collection($roles));
    }

    /**
     * Get role
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\Rbac\RoleResource
     * @apiResourceModel Spatie\Permission\Models\Role with=permissions
     */
    public function show(Request $request, Role $role)
    {
        $role = $this->resolveGuardedRole($role);

        return $this->respondResource($request, new RoleResource($role->load('permissions')));
    }

    /**
     * Create role
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     *
     * @bodyParam name string required Example: editor
     * @bodyParam guard_name string Optional Example: web
     * @apiResource NinjaPortal\Api\Http\Resources\Rbac\RoleResource status=201
     * @apiResourceModel Spatie\Permission\Models\Role with=permissions
     */
    public function store(StoreRoleRequest $request)
    {
        $guard = (string) config('portal-api.auth.guards.admin', 'admin');
        $data = $request->validated();

        $permissionIds = $data['permission_ids'] ?? null;
        unset($data['permission_ids']);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $guard,
        ]);

        if (is_array($permissionIds)) {
            $ids = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('id', $permissionIds)
                ->pluck('id')
                ->all();
            $role->permissions()->sync($ids);
        }

        return $this->respondResource($request, new RoleResource($role->load('permissions')), 'Role created.', status: 201);
    }

    /**
     * Update role
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     *
     * @bodyParam name string Example: editor
     * @apiResource NinjaPortal\Api\Http\Resources\Rbac\RoleResource
     * @apiResourceModel Spatie\Permission\Models\Role with=permissions
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role = $this->resolveGuardedRole($role);
        $data = $request->validated();

        $role->fill($data)->save();

        return $this->respondResource($request, new RoleResource($role->load('permissions')), 'Role updated.');
    }

    /**
     * Delete role
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     *
     * @response 200 {"message":"Role deleted."}
     */
    public function destroy(Role $role)
    {
        $role = $this->resolveGuardedRole($role);
        $role->delete();

        return response()->success('Role deleted.');
    }

    /**
     * Sync role permissions
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     *
     * @bodyParam permission_ids array required Example: [1,2]
     * @apiResource NinjaPortal\Api\Http\Resources\Rbac\RoleResource
     * @apiResourceModel Spatie\Permission\Models\Role with=permissions
     */
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role)
    {
        $role = $this->resolveGuardedRole($role);
        $guard = (string) config('portal-api.auth.guards.admin', 'admin');
        $ids = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('id', $request->validated()['permission_ids'])
            ->pluck('id')
            ->all();
        $role->permissions()->sync($ids);

        return $this->respondResource($request, new RoleResource($role->load('permissions')), 'Role permissions synced.');
    }

    protected function resolveGuardedRole(Role $role): Role
    {
        $guard = (string) config('portal-api.auth.guards.admin', 'admin');
        abort_if($role->guard_name !== $guard, 404);

        return $role;
    }
}
