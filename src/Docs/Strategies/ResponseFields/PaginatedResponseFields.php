<?php

namespace NinjaPortal\Api\Docs\Strategies\ResponseFields;

use Knuckles\Scribe\Extracting\Strategies\TagStrategyWithFormRequestFallback;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Adds reusable response field descriptions for paginated endpoints.
 *
 * Usage:
 * - Add `@paginatedResponse` to a controller method (or class) doc block.
 */
class PaginatedResponseFields extends TagStrategyWithFormRequestFallback
{
    protected string $tagName = 'paginatedResponse';

    public function getFromTags(array $tagsOnMethod, array $tagsOnClass = []): array
    {
        $tag = $this->findTag(array_merge($tagsOnClass, $tagsOnMethod));

        if (! $tag) {
            return [];
        }

        return $this->defaultFields($tag);
    }

    /**
     * @param  Tag[]  $tags
     */
    protected function findTag(array $tags): ?Tag
    {
        foreach ($tags as $tag) {
            if (strtolower($tag->getName()) === strtolower($this->tagName)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function defaultFields(Tag $tag): array
    {
        $itemsDescription = trim((string) ($tag->getContent() ?? ''));
        if ($itemsDescription === '') {
            $itemsDescription = 'Collection of items for the current page.';
        }

        return [
            'success' => [
                'name' => 'success',
                'type' => 'boolean',
                'description' => 'Indicates whether the request completed successfully.',
                'example' => true,
                'required' => true,
            ],
            'status' => [
                'name' => 'status',
                'type' => 'integer',
                'description' => 'HTTP status code.',
                'example' => 200,
                'required' => true,
            ],
            'message' => [
                'name' => 'message',
                'type' => 'string',
                'description' => 'Optional message accompanying the response.',
                'example' => '',
                'required' => true,
            ],
            'data' => [
                'name' => 'data',
                'type' => 'object[]',
                'description' => $itemsDescription,
                'required' => true,
            ],
            'meta' => [
                'name' => 'meta',
                'type' => 'object',
                'description' => 'Response metadata.',
                'required' => true,
            ],
            'meta.pagination' => [
                'name' => 'meta.pagination',
                'type' => 'object',
                'description' => 'Portal pagination summary metadata.',
                'required' => true,
            ],
            'meta.pagination.total' => [
                'name' => 'meta.pagination.total',
                'type' => 'integer',
                'description' => 'Total number of items across all pages.',
                'required' => true,
            ],
            'meta.pagination.count' => [
                'name' => 'meta.pagination.count',
                'type' => 'integer',
                'description' => 'Number of items in the current page.',
                'required' => true,
            ],
            'meta.pagination.per_page' => [
                'name' => 'meta.pagination.per_page',
                'type' => 'integer',
                'description' => 'Items per page.',
                'required' => true,
            ],
            'meta.pagination.current_page' => [
                'name' => 'meta.pagination.current_page',
                'type' => 'integer',
                'description' => 'Current page number.',
                'required' => true,
            ],
            'meta.pagination.total_pages' => [
                'name' => 'meta.pagination.total_pages',
                'type' => 'integer',
                'description' => 'Total number of pages.',
                'required' => true,
            ],
            'meta.pagination.links' => [
                'name' => 'meta.pagination.links',
                'type' => 'object',
                'description' => 'Convenience next/previous pagination links.',
                'required' => true,
            ],
            'meta.pagination.links.next' => [
                'name' => 'meta.pagination.links.next',
                'type' => 'string|null',
                'description' => 'URL for the next page, if available.',
                'required' => false,
            ],
            'meta.pagination.links.prev' => [
                'name' => 'meta.pagination.links.prev',
                'type' => 'string|null',
                'description' => 'URL for the previous page, if available.',
                'required' => false,
            ],
        ];
    }
}
