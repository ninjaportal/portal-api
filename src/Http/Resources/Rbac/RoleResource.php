<?php

namespace NinjaPortal\Api\Http\Resources\Rbac;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->resource;

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions?->map(fn ($p) => [
                'id' => $p->getKey(),
                'name' => $p->name,
                'guard_name' => $p->guard_name,
            ])->values(),
        ];
    }
}
