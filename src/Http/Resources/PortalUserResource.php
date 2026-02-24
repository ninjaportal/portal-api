<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->getKey(),
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'status' => $user->status,
            'sync_with_apigee' => (bool) ($user->sync_with_apigee ?? false),
            'custom_attributes' => $user->custom_attributes ?? [],
            'audiences' => $user->audiences?->map(fn ($audience) => [
                'id' => $audience->getKey(),
                'name' => $audience->name,
            ])->values(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
