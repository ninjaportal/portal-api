<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->resource;

        return [
            'id' => $category->getKey(),
            'slug' => $category->slug,
            'name' => $category->name,
            'short_description' => $category->short_description,
            'description' => $category->description,
            'thumbnail' => $category->thumbnail,
            'translations' => $category->translations?->map(fn ($t) => [
                'locale' => $t->locale,
                'name' => $t->name,
                'short_description' => $t->short_description,
                'description' => $t->description,
                'thumbnail' => $t->thumbnail ?? null,
            ])->values(),
            'created_at' => $category->created_at?->toISOString(),
            'updated_at' => $category->updated_at?->toISOString(),
        ];
    }
}
