<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $setting = $this->resource;

        return [
            'id' => $setting->getKey(),
            'key' => $setting->key,
            'value' => $setting->value,
            'label' => $setting->label,
            'type' => $setting->type,
            'setting_group_id' => $setting->setting_group_id,
            'group' => $setting->relationLoaded('group') && $setting->group
                ? ['id' => $setting->group->getKey(), 'name' => $setting->group->name]
                : null,
            'created_at' => $setting->created_at?->toISOString(),
            'updated_at' => $setting->updated_at?->toISOString(),
        ];
    }
}
