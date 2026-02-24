<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        $name = $user->name
            ?? $user->full_name
            ?? trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
            ?: null;

        return [
            'id' => method_exists($user, 'getKey') ? $user->getKey() : null,
            'email' => $user->email ?? null,
            'name' => $name,
            'first_name' => $user->first_name ?? null,
            'last_name' => $user->last_name ?? null,
            'full_name' => $user->full_name ?? $name,
            'status' => $user->status ?? null,
        ];
    }
}
