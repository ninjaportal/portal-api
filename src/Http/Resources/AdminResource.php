<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $admin = $this->resource;

        return [
            'id' => method_exists($admin, 'getKey') ? $admin->getKey() : null,
            'name' => $admin->name ?? null,
            'email' => $admin->email ?? null,
            'roles' => $admin->roles?->map(fn ($role) => [
                'id' => $role->getKey(),
                'name' => $role->name,
            ])->values(),
            'created_at' => $admin->created_at?->toISOString(),
            'updated_at' => $admin->updated_at?->toISOString(),
        ];
    }
}

