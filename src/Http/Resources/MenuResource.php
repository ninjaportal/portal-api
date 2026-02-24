<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $menu = $this->resource;

        return [
            'id' => $menu->getKey(),
            // Avoid colliding with Menu::slug(...) helper when building Scribe example models.
            'slug' => method_exists($menu, 'getRawOriginal')
                ? $menu->getRawOriginal('slug')
                : (($menu->getAttributes()['slug'] ?? null)),
            'items' => $menu->items?->map(fn ($item) => (new MenuItemResource($item))->resolve($request))->values(),
            'created_at' => $menu->created_at?->toISOString(),
            'updated_at' => $menu->updated_at?->toISOString(),
        ];
    }
}
