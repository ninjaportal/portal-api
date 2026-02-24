<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreAdminRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateAdminRequest;
use NinjaPortal\Api\Http\Resources\AdminResource;
use NinjaPortal\Portal\Contracts\Services\AdminServiceInterface;
use NinjaPortal\Portal\Events\Admin\AdminRolesSyncedEvent;
use NinjaPortal\Portal\Models\Admin;
use Spatie\Permission\Models\Role;

/**
 * @group Admin: Admins
 */
class AdminsController extends Controller
{
    public function __construct(protected AdminServiceInterface $admins) {}

    /**
     * List admins
     *
     * Requires permission: `portal.admins.manage`.
     *
     * @authenticated
     *
     * @queryParam search string Example: owner
     * @paginateRequest
     *
     * @paginatedResponse
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\AdminResource paginate=15
     * @apiResourceModel NinjaPortal\Portal\Models\Admin with=roles
     */
    public function index(Request $request)
    {
        $this->authorizeCrudIndex(Admin::class);

        [$perPage, $orderBy, $direction] = $this->listParams(
            $request,
            ['id', 'name', 'email', 'created_at', 'updated_at'],
            'id'
        );

        $search = trim((string) $request->query('search', ''));

        $query = $this->admins->query()->with('roles');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        $paginator = $query
            ->orderBy($orderBy, strtolower($direction))
            ->paginate($perPage);

        return $this->respondPaginated($request, $paginator, AdminResource::class);
    }

    /**
     * Create admin
     *
     * Requires permission: `portal.admins.manage`.
     *
     * @authenticated
     *
     * @bodyParam name string required Example: Portal Owner
     * @bodyParam email string required Example: owner@example.com
     * @bodyParam password string required Example: secret123
     * @bodyParam role_ids array Optional Example: [1,2]
     * @apiResource NinjaPortal\Api\Http\Resources\AdminResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\Admin with=roles
     */
    public function store(StoreAdminRequest $request)
    {
        $this->authorizeCrudCreate(Admin::class);

        $data = $request->validated();

        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids']);

        $data['password'] = Hash::make((string) $data['password']);

        /** @var Admin $admin */
        $admin = $this->admins->create($data);

        if (is_array($roleIds) && method_exists($admin, 'syncRoles')) {
            $roles = Role::query()->whereIn('id', $roleIds)->pluck('name')->all();
            $admin->syncRoles($roles);
            AdminRolesSyncedEvent::dispatch($admin, $roleIds);
        }

        $admin->loadMissing('roles');

        return $this->respondResource($request, new AdminResource($admin), 'Admin created.', status: 201);
    }

    /**
     * Get admin
     *
     * Requires permission: `portal.admins.manage`.
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\AdminResource
     * @apiResourceModel NinjaPortal\Portal\Models\Admin with=roles
     */
    public function show(Request $request, int $admin)
    {
        $model = $this->admins->query()->with('roles')->findOrFail($admin);
        $this->authorizeCrudView($model);

        return $this->respondResource($request, new AdminResource($model));
    }

    /**
     * Update admin
     *
     * Requires permission: `portal.admins.manage`.
     *
     * @authenticated
     *
     * @bodyParam name string Example: Portal Owner
     * @bodyParam email string Example: owner@example.com
     * @bodyParam password string Example: secret123
     * @bodyParam role_ids array Optional Example: [1,2]
     * @apiResource NinjaPortal\Api\Http\Resources\AdminResource
     * @apiResourceModel NinjaPortal\Portal\Models\Admin with=roles
     */
    public function update(UpdateAdminRequest $request, int $admin)
    {
        $existing = $this->admins->query()->findOrFail($admin);
        $this->authorizeCrudUpdate($existing);

        $data = $request->validated();

        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids']);

        if (array_key_exists('password', $data)) {
            $data['password'] = Hash::make((string) $data['password']);
        }

        /** @var Admin $model */
        $model = $this->admins->update($admin, $data);

        if (is_array($roleIds) && method_exists($model, 'syncRoles')) {
            $roles = Role::query()->whereIn('id', $roleIds)->pluck('name')->all();
            $model->syncRoles($roles);
            AdminRolesSyncedEvent::dispatch($model, $roleIds);
        }

        $model->loadMissing('roles');

        return $this->respondResource($request, new AdminResource($model), 'Admin updated.');
    }

    /**
     * Delete admin
     *
     * Requires permission: `portal.admins.manage`.
     *
     * @authenticated
     *
     * @response 200 {"message":"Admin deleted."}
     */
    public function destroy(Request $request, int $admin)
    {
        if ((int) (auth()->id() ?? 0) === (int) $admin) {
            return response()->forbidden('You cannot delete your own account.');
        }

        $model = $this->admins->query()->findOrFail($admin);
        $this->authorizeCrudDelete($model);

        $this->admins->delete((int) $admin);

        return response()->success('Admin deleted.');
    }
}
