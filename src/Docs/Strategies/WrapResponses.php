<?php

namespace NinjaPortal\Api\Docs\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\RouteDocBlocker;
use Knuckles\Scribe\Extracting\Strategies\Strategy;

/**
 * Ensures response examples in generated docs use the standard NinjaPortal API envelope:
 * Success: { success, status, message, data }
 * Error:   { success, status, message, errors }
 *
 * This is primarily useful for responses extracted from @response tags or non-GET endpoints
 * where Scribe may not execute live response calls.
 */
class WrapResponses extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        $defaultMessage = (string) ($settings['default_message'] ?? '');
        $messageField = $settings['message_field'] ?? null;
        $shouldPaginate = $this->hasPaginatedResponseTag($endpointData);

        foreach ($endpointData->responses as &$resp) {
            $statusCode = (int) ($resp['status'] ?? 200);
            $original = $resp['content'] ?? '';

            [$decoded, $raw] = $this->decodeMaybeJson($original, $statusCode);

            if (is_array($decoded) && $this->isAlreadyWrapped($decoded)) {
                continue;
            }

            $ok = $statusCode >= 200 && $statusCode < 300;

            $message = $defaultMessage;
            if (is_array($decoded)) {
                if (is_string($messageField) && array_key_exists($messageField, $decoded)) {
                    $message = (string) $decoded[$messageField];
                } elseif (array_key_exists('message', $decoded)) {
                    $message = (string) $decoded['message'];
                }
            }

            $payload = $decoded ?? $raw;
            $responseMeta = null;
            if (is_array($payload)) {
                if (is_string($messageField) && array_key_exists($messageField, $payload)) {
                    unset($payload[$messageField]);
                }
                if (array_key_exists('message', $payload) && is_string($payload['message'])) {
                    unset($payload['message']);
                }
            }

            if ($ok && $shouldPaginate) {
                [$payload, $responseMeta] = $this->ensurePaginatedPayload($payload, $endpointData);
            } else {
                // Scribe's @apiResource examples are often wrapped in Laravel's default resource
                // envelope: {"data": ...}. Unwrap before applying the portal envelope to avoid
                // docs examples like { data: { data: {...} } } for non-paginated endpoints.
                $payload = $this->unwrapScribeResourceEnvelope($payload);
            }

            if ($ok) {
                $wrapped = [
                    'success' => true,
                    'status' => $statusCode,
                    'message' => $message,
                    'data' => empty($payload) ? new \stdClass : $payload,
                ];
                if (is_array($responseMeta) && $responseMeta !== []) {
                    $wrapped['meta'] = $responseMeta;
                }
            } else {
                $errors = [];
                if (is_array($payload) && array_key_exists('errors', $payload) && is_array($payload['errors'])) {
                    $errors = $payload['errors'];
                }

                $wrapped = [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $message !== '' ? $message : 'Request failed.',
                    'errors' => empty($errors) ? new \stdClass : $errors,
                ];
            }

            $resp['content'] = json_encode(
                $wrapped,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
        }

        return null;
    }

    protected function hasPaginatedResponseTag(ExtractedEndpointData $endpointData): bool
    {
        if (! $endpointData->route) {
            return false;
        }

        $docBlocks = RouteDocBlocker::getDocBlocksFromRoute($endpointData->route);
        $tags = array_merge(
            $docBlocks['class']?->getTags() ?? [],
            $docBlocks['method']?->getTags() ?? [],
        );

        foreach ($tags as $tag) {
            if (strtolower($tag->getName()) === 'paginatedresponse') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: array<mixed>|null, 1: string|array}
     */
    protected function decodeMaybeJson(mixed $original, int $statusCode): array
    {
        if (is_array($original)) {
            return [$original, $original];
        }

        if (! is_string($original)) {
            return [null, (string) $original];
        }

        $raw = trim($original);
        $candidate = $raw;

        if (preg_match('/^(\\d{3})\\s+([\\[{].*)$/s', $raw, $matches)) {
            $code = (int) $matches[1];
            if ($code === (int) $statusCode) {
                $candidate = $matches[2];
            }
        }

        $decoded = json_decode($candidate, true);
        if (is_null($decoded) && $candidate !== $raw) {
            $decoded = json_decode($raw, true);
        }

        return [is_array($decoded) ? $decoded : null, $raw];
    }

    /**
     * @param  array<mixed>  $payload
     */
    protected function isAlreadyWrapped(array $payload): bool
    {
        $hasCommon = array_key_exists('success', $payload)
            && array_key_exists('status', $payload)
            && array_key_exists('message', $payload);

        if (! $hasCommon) {
            return false;
        }

        return array_key_exists('data', $payload) || array_key_exists('errors', $payload);
    }

    /**
     * @return array{0: mixed, 1: array<string, mixed>}
     */
    protected function ensurePaginatedPayload(mixed $payload, ExtractedEndpointData $endpointData): array
    {
        // Flatten Laravel paginated resource payloads to match runtime shape:
        // top-level data = items[], top-level meta.pagination = summary.
        if (is_array($payload) && $this->looksLikeLaravelPaginatedResourcePayload($payload)) {
            return [$payload['data'], $this->buildTopLevelPaginationMetaFromResourcePayload($payload)];
        }

        if (is_array($payload) && $this->looksLikeLengthAwarePaginator($payload)) {
            $items = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            return [$items, $this->buildTopLevelPaginationMetaFromLengthAwarePayload($payload)];
        }

        $payload = $this->unwrapScribeResourceEnvelope($payload);
        $items = $this->extractItems($payload);

        $baseUrl = rtrim($this->config->get('base_url') ?? config('app.url', ''), '/');
        $resourcePath = trim($endpointData->uri, '/') ?: 'items';

        $pageUrl = static function (int $page) use ($baseUrl, $resourcePath): string {
            $baseUrl = $baseUrl !== '' ? $baseUrl : 'https://api.example.com';

            return sprintf('%s/%s?page=%d', $baseUrl, $resourcePath, $page);
        };

        $path = ($baseUrl !== '' ? $baseUrl : 'https://api.example.com').'/'.$resourcePath;

        return [
            $items,
            [
                'pagination' => [
                    'total' => 150,
                    'count' => count($items),
                    'per_page' => 15,
                    'current_page' => 1,
                    'total_pages' => 10,
                    'links' => [
                        'next' => $pageUrl(2),
                        'prev' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<mixed>  $payload
     */
    protected function looksLikeLaravelPaginatedResourcePayload(array $payload): bool
    {
        return array_key_exists('data', $payload)
            && is_array($payload['data'])
            && array_key_exists('links', $payload)
            && is_array($payload['links'])
            && array_key_exists('meta', $payload)
            && is_array($payload['meta']);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildTopLevelPaginationMetaFromResourcePayload(array $payload): array
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $links = is_array($payload['links'] ?? null) ? $payload['links'] : [];
        $items = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return [
            'pagination' => [
                'total' => (int) ($meta['total'] ?? count($items)),
                'count' => count($items),
                'per_page' => (int) ($meta['per_page'] ?? count($items)),
                'current_page' => (int) ($meta['current_page'] ?? 1),
                'total_pages' => (int) ($meta['last_page'] ?? 1),
                'links' => [
                    'next' => $links['next'] ?? null,
                    'prev' => $links['prev'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param  array<mixed>  $payload
     */
    protected function looksLikeLengthAwarePaginator(array $payload): bool
    {
        return array_key_exists('current_page', $payload)
            && array_key_exists('data', $payload)
            && array_key_exists('total', $payload)
            && array_key_exists('per_page', $payload);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildTopLevelPaginationMetaFromLengthAwarePayload(array $payload): array
    {
        $items = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return [
            'pagination' => [
                'total' => (int) ($payload['total'] ?? count($items)),
                'count' => count($items),
                'per_page' => (int) ($payload['per_page'] ?? 15),
                'current_page' => (int) ($payload['current_page'] ?? 1),
                'total_pages' => (int) ($payload['last_page'] ?? 1),
                'links' => [
                    'next' => $payload['next_page_url'] ?? null,
                    'prev' => $payload['prev_page_url'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function extractItems(mixed $payload): array
    {
        if (is_array($payload)) {
            if ($this->isList($payload)) {
                return $payload;
            }

            if (array_key_exists('data', $payload) && is_array($payload['data']) && $this->isList($payload['data'])) {
                return $payload['data'];
            }

            return [$payload];
        }

        if ($payload === null || $payload === '') {
            return [];
        }

        return [$payload];
    }

    protected function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    protected function unwrapScribeResourceEnvelope(mixed $payload): mixed
    {
        if (! is_array($payload) || ! array_key_exists('data', $payload)) {
            return $payload;
        }

        // Limit unwrapping to the typical Laravel JsonResource/ResourceCollection envelopes.
        $allowedKeys = ['data', 'links', 'meta'];
        foreach (array_keys($payload) as $key) {
            if (! in_array((string) $key, $allowedKeys, true)) {
                return $payload;
            }
        }

        return $payload['data'];
    }
}
