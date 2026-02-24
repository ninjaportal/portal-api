<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'slug' => $this->resource->slug,
            'visibility' => $this->resource->visibility,
            'apigee_product_id' => $this->resource->apigee_product_id,
            'swagger_url' => $this->resource->swagger_url,
            'integration_file' => $this->resource->integration_file,
            'tags' => is_array($this->resource->tags) ? array_values($this->resource->tags) : [],
            'custom_attributes' => is_array($this->resource->custom_attributes) ? $this->resource->custom_attributes : [],
            'name' => $this->resource->name,
            'short_description' => $this->resource->short_description,
            'description' => $this->resource->description,
            'thumbnail' => $this->resource->thumbnail,
            'translations' => $this->resource->translations?->map(fn ($t) => [
                'locale' => $t->locale,
                'name' => $t->name,
                'short_description' => $t->short_description,
                'description' => $t->description,
                'thumbnail' => $t->thumbnail,
            ])->values(),
            'categories' => $this->resource->categories?->map(fn ($category) => [
                'id' => $category->getKey(),
                'slug' => $category->slug,
                'name' => $category->name,
            ])->values(),
            'audiences' => $this->resource->audiences?->map(fn ($audience) => [
                'id' => $audience->getKey(),
                'name' => $audience->name,
            ])->values(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
