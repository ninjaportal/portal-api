<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\MenuItemResource;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreMenuItemRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateMenuItemRequest;
use NinjaPortal\Portal\Contracts\Services\MenuItemServiceInterface;
use NinjaPortal\Portal\Models\Menu;
use NinjaPortal\Portal\Models\MenuItem;

/**
 * @group Admin: Menu Items
 */
class MenuItemsController extends Controller
{
    public function __construct(protected MenuItemServiceInterface $items) {}

    /**
     * List menu items
     *
     * @authenticated
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\MenuItemResource
     * @apiResourceModel NinjaPortal\Portal\Models\MenuItem with=translations
     */
    public function index(Request $request, Menu $menu)
    {
        $this->authorizeCrudView($menu);
        $this->authorizeCrudIndex(MenuItem::class);

        $query = $this->items->query()
            ->where('menu_id', $menu->getKey())
            ->with('translations')
            ->orderByDesc('id');

        return response()->success(MenuItemResource::collection($query->get()));
    }

    /**
     * Create menu item
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\MenuItemResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\MenuItem with=translations
     */
    public function store(StoreMenuItemRequest $request, Menu $menu)
    {
        $this->authorizeCrudUpdate($menu);
        $this->authorizeCrudCreate(MenuItem::class);

        /** @var MenuItem $created */
        $created = $this->items->create(array_merge($request->validated(), [
            'menu_id' => $menu->getKey(),
        ]));

        $created->loadMissing('translations');

        return $this->respondResource($request, new MenuItemResource($created), 'Menu item created.', status: 201);
    }

    /**
     * Get menu item
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\MenuItemResource
     * @apiResourceModel NinjaPortal\Portal\Models\MenuItem with=translations
     */
    public function show(Request $request, Menu $menu, MenuItem $menuItem)
    {
        abort_if((int) $menuItem->menu_id !== (int) $menu->getKey(), 404);
        $this->authorizeCrudView($menu);
        $this->authorizeCrudView($menuItem);

        $menuItem->loadMissing('translations');

        return $this->respondResource($request, new MenuItemResource($menuItem));
    }

    /**
     * Update menu item
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\MenuItemResource
     * @apiResourceModel NinjaPortal\Portal\Models\MenuItem with=translations
     */
    public function update(UpdateMenuItemRequest $request, Menu $menu, MenuItem $menuItem)
    {
        abort_if((int) $menuItem->menu_id !== (int) $menu->getKey(), 404);
        $this->authorizeCrudUpdate($menu);
        $this->authorizeCrudUpdate($menuItem);

        /** @var MenuItem $updated */
        $updated = $this->items->update($menuItem, $request->validated());
        $updated->loadMissing('translations');

        return $this->respondResource($request, new MenuItemResource($updated), 'Menu item updated.');
    }

    /**
     * Delete menu item
     *
     * @authenticated
     */
    public function destroy(Menu $menu, MenuItem $menuItem)
    {
        abort_if((int) $menuItem->menu_id !== (int) $menu->getKey(), 404);
        $this->authorizeCrudUpdate($menu);
        $this->authorizeCrudDelete($menuItem);

        $this->items->delete($menuItem->getKey());

        return response()->success('Menu item deleted.');
    }
}
