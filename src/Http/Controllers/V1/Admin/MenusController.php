<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\MenuResource;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreMenuRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateMenuRequest;
use NinjaPortal\Portal\Contracts\Services\MenuServiceInterface;
use NinjaPortal\Portal\Models\Menu;

/**
 * @group Admin: Menus
 */
class MenusController extends Controller
{
    public function __construct(protected MenuServiceInterface $menus) {}

    /**
     * List menus
     *
     * @authenticated
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\MenuResource
     * @apiResourceModel NinjaPortal\Portal\Models\Menu with=items,items.translations
     */
    public function index(Request $request)
    {
        $this->authorizeCrudIndex(Menu::class);

        return response()->success(MenuResource::collection(
            $this->menus->query()->with('items.translations')->orderByDesc('id')->get()
        ));
    }

    /**
     * Create menu
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\MenuResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\Menu with=items,items.translations
     */
    public function store(StoreMenuRequest $request)
    {
        $this->authorizeCrudCreate(Menu::class);

        /** @var Menu $created */
        $created = $this->menus->create($request->validated());
        $created->loadMissing('items.translations');

        return $this->respondResource($request, new MenuResource($created), 'Menu created.', status: 201);
    }

    /**
     * Get menu
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\MenuResource
     * @apiResourceModel NinjaPortal\Portal\Models\Menu with=items,items.translations
     */
    public function show(Request $request, Menu $menu)
    {
        $this->authorizeCrudView($menu);
        $menu->loadMissing('items.translations');

        return $this->respondResource($request, new MenuResource($menu));
    }

    /**
     * Update menu
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\MenuResource
     * @apiResourceModel NinjaPortal\Portal\Models\Menu with=items,items.translations
     */
    public function update(UpdateMenuRequest $request, Menu $menu)
    {
        $this->authorizeCrudUpdate($menu);
        /** @var Menu $updated */
        $updated = $this->menus->update($menu, $request->validated());
        $updated->loadMissing('items.translations');

        return $this->respondResource($request, new MenuResource($updated), 'Menu updated.');
    }

    /**
     * Delete menu
     *
     * @authenticated
     */
    public function destroy(Menu $menu)
    {
        $this->authorizeCrudDelete($menu);
        $this->menus->delete($menu->getKey());

        return response()->success('Menu deleted.');
    }
}
