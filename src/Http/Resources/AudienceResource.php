<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AudienceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $audience = $this->resource;

        return [
            'id' => $audience->getKey(),
            'name' => $audience->name,
            'users' => $audience->users?->map(fn ($user) => [
                'id' => $user->getKey(),
                'email' => $user->email,
                'full_name' => $user->full_name,
                'status' => $user->status,
            ])->values(),
            'products' => $audience->products?->map(fn ($product) => [
                'id' => $product->getKey(),
                'slug' => $product->slug,
                'name' => $product->name,
                'visibility' => $product->visibility,
            ])->values(),
            'created_at' => $audience->created_at?->toISOString(),
            'updated_at' => $audience->updated_at?->toISOString(),
        ];
    }
}
