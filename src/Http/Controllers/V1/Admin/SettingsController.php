<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\SettingResource;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreSettingRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateSettingRequest;
use NinjaPortal\Portal\Contracts\Services\SettingServiceInterface;
use NinjaPortal\Portal\Models\Setting;

/**
 * @group Admin: Settings
 */
class SettingsController extends Controller
{
    public function __construct(protected SettingServiceInterface $settings) {}

    /**
     * List settings
     *
     * @authenticated
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\SettingResource
     * @apiResourceModel NinjaPortal\Portal\Models\Setting with=group
     */
    public function index(Request $request)
    {
        $this->authorizeCrudIndex(Setting::class);

        $query = $this->settings->query()->with('group')->orderBy('key');

        return response()->success(SettingResource::collection($query->get()));
    }

    /**
     * Create setting
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\SettingResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\Setting with=group
     */
    public function store(StoreSettingRequest $request)
    {
        $this->authorizeCrudCreate(Setting::class);

        /** @var Setting $created */
        $created = $this->settings->create($request->validated());
        $created->loadMissing('group');

        return $this->respondResource($request, new SettingResource($created), 'Setting created.', status: 201);
    }

    /**
     * Get setting
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\SettingResource
     * @apiResourceModel NinjaPortal\Portal\Models\Setting with=group
     */
    public function show(Request $request, Setting $setting)
    {
        $this->authorizeCrudView($setting);
        $setting->loadMissing('group');

        return $this->respondResource($request, new SettingResource($setting));
    }

    /**
     * Update setting
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\SettingResource
     * @apiResourceModel NinjaPortal\Portal\Models\Setting with=group
     */
    public function update(UpdateSettingRequest $request, Setting $setting)
    {
        $this->authorizeCrudUpdate($setting);

        /** @var Setting $updated */
        $updated = $this->settings->update($setting, $request->validated());
        $this->settings->set($setting->key, $updated->value, $setting->type);
        $updated->loadMissing('group');

        return $this->respondResource($request, new SettingResource($updated), 'Setting updated.');
    }

    /**
     * Delete setting
     *
     * @authenticated
     */
    public function destroy(Setting $setting)
    {
        $this->authorizeCrudDelete($setting);
        $this->settings->delete($setting->getKey());

        return response()->success('Setting deleted.');
    }
}
