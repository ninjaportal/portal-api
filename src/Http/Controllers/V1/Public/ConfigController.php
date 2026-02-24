<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Public;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\SettingResource;
use NinjaPortal\Portal\Contracts\Services\SettingServiceInterface;

/**
 * @group Public: Config
 */
class ConfigController extends Controller
{
    public function __construct(protected SettingServiceInterface $settings) {}

    /**
     * Get public portal settings
     */
    public function __invoke(Request $request)
    {
        $query = $this->settings->query()->with('group')->orderBy('key');

        $groups = (array) config('portal-api.public_settings.groups', []);
        $keys = (array) config('portal-api.public_settings.keys', []);

        if ($groups !== []) {
            $query->whereHas('group', fn ($builder) => $builder->whereIn('name', $groups));
        }

        if ($keys !== []) {
            $query->whereIn('key', $keys);
        }

        $settings = $query->get();
        $values = $settings->mapWithKeys(fn ($setting) => [
            $setting->key => $this->settings->get($setting->key),
        ])->toArray();

        return response()->success([
            'settings' => SettingResource::collection($settings),
            'values' => $values,
        ]);
    }
}
