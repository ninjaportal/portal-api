<?php

namespace NinjaPortal\Api\Http\Resources\Rbac;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'id' => $p->getKey(),
            'name' => $p->name,
            'guard_name' => $p->guard_name,
        ];
    }
}
