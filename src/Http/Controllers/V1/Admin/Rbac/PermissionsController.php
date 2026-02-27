<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin\Rbac;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\Rbac\PermissionResource;
use NinjaPortal\Api\Http\Requests\V1\Admin\StorePermissionRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdatePermissionRequest;
use Spatie\Permission\Models\Permission;

/**
 * @group Admin: RBAC
 */
class PermissionsController extends Controller
{
    /**
     * List permissions
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\Rbac\PermissionResource
     * @apiResourceModel Spatie\Permission\Models\Permission
     */
    public function index(Request $request)
    {
        $guard = (string) config('portal-api.auth.guards.admin', 'admin');
        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->orderBy('name')
            ->get();

        return response()->success(PermissionResource::collection($permissions));
    }

    /**
     * Get permission
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\Rbac\PermissionResource
     * @apiResourceModel Spatie\Permission\Models\Permission
     */
    public function show(Request $request, Permission $permission)
    {
        $permission = $this->resolveGuardedPermission($permission);

        return $this->respondResource($request, new PermissionResource($permission));
    }

    /**
     * Create permission
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     *
     * @bodyParam name string required Example: portal.products.manage
     * @bodyParam guard_name string Optional Example: web
     * @apiResource NinjaPortal\Api\Http\Resources\Rbac\PermissionResource status=201
     * @apiResourceModel Spatie\Permission\Models\Permission
     */
    public function store(StorePermissionRequest $request)
    {
        $guard = (string) config('portal-api.auth.guards.admin', 'admin');
        $data = $request->validated();

        $permission = Permission::create([
            'name' => $data['name'],
            'guard_name' => $guard,
        ]);

        return $this->respondResource($request, new PermissionResource($permission), 'Permission created.', status: 201);
    }

    /**
     * Update permission
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     *
     * @bodyParam name string Example: portal.products.manage
     * @apiResource NinjaPortal\Api\Http\Resources\Rbac\PermissionResource
     * @apiResourceModel Spatie\Permission\Models\Permission
     */
    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $permission = $this->resolveGuardedPermission($permission);
        $data = $request->validated();

        $permission->fill($data)->save();

        return $this->respondResource($request, new PermissionResource($permission), 'Permission updated.');
    }

    /**
     * Delete permission
     *
     * Requires permission: `portal.rbac.manage`.
     *
     * @authenticated
     *
     * @response 200 {"message":"Permission deleted."}
     */
    public function destroy(Permission $permission)
    {
        $permission = $this->resolveGuardedPermission($permission);
        $permission->delete();

        return response()->success('Permission deleted.');
    }

    protected function resolveGuardedPermission(Permission $permission): Permission
    {
        $guard = (string) config('portal-api.auth.guards.admin', 'admin');
        abort_if($permission->guard_name !== $guard, 404);

        return $permission;
    }
}
