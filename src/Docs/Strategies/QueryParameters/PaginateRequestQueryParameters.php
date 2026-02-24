<?php

namespace NinjaPortal\Api\Docs\Strategies\QueryParameters;

use Knuckles\Scribe\Extracting\Strategies\TagStrategyWithFormRequestFallback;
use Mpociot\Reflection\DocBlock\Tag;

/**
 * Adds shared pagination/sorting query parameters when `@paginateRequest` is present.
 *
 * This keeps controller PHPDocs small while documenting the shared contract.
 */
class PaginateRequestQueryParameters extends TagStrategyWithFormRequestFallback
{
    protected string $tagName = 'paginateRequest';

    public function getFromTags(array $tagsOnMethod, array $tagsOnClass = []): array
    {
        $tag = $this->findTag([...$tagsOnClass, ...$tagsOnMethod]);

        if (! $tag) {
            return [];
        }

        return $this->defaultParameters();
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
    protected function defaultParameters(): array
    {
        return [
            'per_page' => [
                'name' => 'per_page',
                'description' => 'Number of items per page (max 100).',
                'required' => false,
                'example' => 15,
                'type' => 'integer',
                'enumValues' => [],
                'exampleWasSpecified' => true,
            ],
            'order_by' => [
                'name' => 'order_by',
                'description' => 'Sort field. Alias: `sortBy`.',
                'required' => false,
                'example' => 'id',
                'type' => 'string',
                'enumValues' => [],
                'exampleWasSpecified' => true,
            ],
            'direction' => [
                'name' => 'direction',
                'description' => 'Sort direction. Alias: `sortDir`.',
                'required' => false,
                'example' => 'DESC',
                'type' => 'string',
                'enumValues' => ['ASC', 'DESC'],
                'exampleWasSpecified' => true,
            ],
            'with' => [
                'name' => 'with',
                'description' => 'Comma-separated list of relations to eager-load.',
                'required' => false,
                'example' => 'audiences,categories',
                'type' => 'string',
                'enumValues' => [],
                'exampleWasSpecified' => true,
            ],
            'filters' => [
                'name' => 'filters',
                'description' => 'JSON-encoded array of filter objects.',
                'required' => false,
                'example' => '[{"field":"status","operator":"=","value":"active"}]',
                'type' => 'string',
                'enumValues' => [],
                'exampleWasSpecified' => true,
            ],
        ];
    }
}

