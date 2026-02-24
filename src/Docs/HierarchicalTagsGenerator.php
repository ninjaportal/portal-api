<?php

namespace NinjaPortal\Api\Docs;

use Illuminate\Support\Arr;
use Knuckles\Camel\Camel;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/**
 * Builds hierarchical OpenAPI tags for Scribe output:
 * - Adds Redoc-compatible `x-tagGroups` for visual nesting.
 * - If an endpoint has a `subgroup`, tags the operation with ["Group / Subgroup", "Group"].
 */
class HierarchicalTagsGenerator extends OpenApiGenerator
{
    public function root(array $root, array $groupedEndpoints): array
    {
        [$allTags, $xTagGroups] = $this->buildHierarchicalTags($groupedEndpoints);

        $root['tags'] = $allTags;
        $root['x-tagGroups'] = $xTagGroups;

        return $root;
    }

    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        $group = Arr::first($groupedEndpoints, function ($g) use ($endpoint) {
            return Camel::doesGroupContainEndpoint($g, $endpoint);
        });

        $groupName = $group['name'] ?? 'Endpoints';
        $subgroupName = $endpoint->metadata->subgroup ?? null;

        if ($subgroupName) {
            $pathItem['tags'] = [$this->composeSubTag($groupName, $subgroupName), $groupName];
        } else {
            $pathItem['tags'] = [$groupName];
        }

        return $pathItem;
    }

    /**
     * @return array{0: array<int, array{name:string,description?:string}>, 1: array<int, array{name:string,tags:array<int,string>}>}
     */
    protected function buildHierarchicalTags(array $groupedEndpoints): array
    {
        $tags = []; // map: tagName => ['name' => ..., 'description' => ...]
        $tagGroups = []; // map: groupName => ['name' => ..., 'tags' => [...]]

        foreach ($groupedEndpoints as $group) {
            $groupName = $group['name'] ?? 'Endpoints';
            $groupDesc = $group['description'] ?? '';

            $tags[$groupName] = $tags[$groupName] ?? [
                'name' => $groupName,
                'description' => $groupDesc,
            ];

            if (! isset($tagGroups[$groupName])) {
                $tagGroups[$groupName] = ['name' => $groupName, 'tags' => []];
            }

            $tagGroups[$groupName]['tags'][] = $groupName;

            $subgroups = [];
            foreach ($group['endpoints'] ?? [] as $ep) {
                $sub = $ep->metadata->subgroup ?? null;
                if ($sub) {
                    $subgroups[$sub] = $subgroups[$sub] ?? ($ep->metadata->subgroupDescription ?? '');
                }
            }

            foreach ($subgroups as $subName => $subDesc) {
                $fullTag = $this->composeSubTag($groupName, $subName);

                $tags[$fullTag] = $tags[$fullTag] ?? [
                    'name' => $fullTag,
                    'description' => $subDesc ?: "{$groupName} — {$subName}",
                ];

                $tagGroups[$groupName]['tags'][] = $fullTag;
            }

            $tagGroups[$groupName]['tags'] = array_values(array_unique($tagGroups[$groupName]['tags']));
        }

        return [array_values($tags), array_values($tagGroups)];
    }

    protected function composeSubTag(string $group, string $subgroup): string
    {
        return "{$group} / {$subgroup}";
    }
}
