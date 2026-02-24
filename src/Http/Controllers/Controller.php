<?php

namespace NinjaPortal\Api\Http\Controllers;

use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NinjaPortal\Api\Contracts\Authorization\ApiAuthorizerInterface;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    protected function perPage(Request $request): int
    {
        $perPageDefault = (int) config('portal-api.pagination.per_page', 15);
        $perPageMax = (int) config('portal-api.pagination.max_per_page', 100);
        $perPage = (int) $request->query('per_page', $perPageDefault);

        return max(1, min($perPage, $perPageMax));
    }

    /**
     * @param  array<int, string>  $allowedSorts
     * @param  array<string, string>  $translatableSorts map: request sort key => translation attribute
     * @return array{0: int, 1: string, 2: string, 3: (Closure(mixed):mixed)|null}
     */
    protected function listParams(Request $request, array $allowedSorts, string $defaultSort = 'id', array $translatableSorts = []): array
    {
        $params = $request->preRequest();

        $sortBy = (string) ($params['order_by'] ?? $defaultSort);
        $direction = strtoupper((string) ($params['direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $perPage = (int) ($params['per_page'] ?? $this->perPage($request));

        $extendQuery = null;
        if ($translatableSorts !== [] && array_key_exists($sortBy, $translatableSorts)) {
            $translationAttribute = $translatableSorts[$sortBy];

            $extendQuery = static function ($query) use ($translationAttribute, $direction) {
                if (method_exists($query, 'orderByTranslation')) {
                    return $query->orderByTranslation($translationAttribute, strtolower($direction));
                }

                return $query;
            };

            $sortBy = $defaultSort;
        }

        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = $defaultSort;
        }

        return [$perPage, $sortBy, $direction, $extendQuery];
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function respondPaginated(Request $request, LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        $resource = $resourceClass::collection($paginator);

        return response()->paginateResource($resource);
    }

    protected function respondResource(Request $request, JsonResource $resource, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->success($message ?? '', $resource->resolve($request), $status);
    }

    /**
     * Store uploaded files or base64 data URLs and return a URL or path.
     */
    protected function storeUploadedValue(mixed $value, string $directory, string $disk = 'public', bool $returnUrl = true): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        if ($value instanceof UploadedFile) {
            $path = $value->store($directory, $disk);

            return $returnUrl ? Storage::disk($disk)->url($path) : $path;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            $data = $this->decodeDataUrl($trimmed);
            if ($data !== null) {
                [$mime, $binary] = $data;
                $extension = $this->extensionFromMime($mime);
                $filename = Str::uuid()->toString().'.'.$extension;
                $path = trim($directory, '/').'/'.$filename;
                Storage::disk($disk)->put($path, $binary);

                return $returnUrl ? Storage::disk($disk)->url($path) : $path;
            }

            return $trimmed;
        }

        return null;
    }

    /**
     * Handle uploadable fields inside locale-keyed translation payloads.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    protected function handleTranslatableUploads(array $data, array $fields, string $directory, string $disk = 'public'): array
    {
        $locales = config('ninjaportal.translatable.locales', ['en', 'ar']);

        foreach ($locales as $locale) {
            if (! isset($data[$locale]) || ! is_array($data[$locale])) {
                continue;
            }

            foreach ($fields as $field) {
                if (! array_key_exists($field, $data[$locale])) {
                    continue;
                }

                $data[$locale][$field] = $this->storeUploadedValue(
                    $data[$locale][$field],
                    $directory.'/'.$locale,
                    $disk
                );
            }
        }

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->storeUploadedValue(
                $data[$field],
                $directory,
                $disk
            );
        }

        return $data;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected function decodeDataUrl(string $value): ?array
    {
        if (! str_starts_with($value, 'data:')) {
            return null;
        }

        if (! preg_match('/^data:(?<mime>[^;]+);base64,(?<data>.+)$/', $value, $matches)) {
            return null;
        }

        $binary = base64_decode($matches['data'], true);
        if ($binary === false) {
            return null;
        }

        return [$matches['mime'], $binary];
    }

    protected function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => Str::before(Str::after($mime, '/'), '+') ?: 'bin',
        };
    }

    protected function authorizeApi(string $ability, mixed $arguments = []): void
    {
        if (! (bool) config('portal-api.authorization.use_policies', true)) {
            return;
        }

        app(ApiAuthorizerInterface::class)->authorize($ability, $arguments);
    }

    protected function authorizeCrudIndex(string $modelClass): void
    {
        $this->authorizeApi('viewAny', $modelClass);
    }

    protected function authorizeCrudCreate(string $modelClass): void
    {
        $this->authorizeApi('create', $modelClass);
    }

    protected function authorizeCrudView(mixed $model): void
    {
        $this->authorizeApi('view', $model);
    }

    protected function authorizeCrudUpdate(mixed $model): void
    {
        $this->authorizeApi('update', $model);
    }

    protected function authorizeCrudDelete(mixed $model): void
    {
        $this->authorizeApi('delete', $model);
    }
}
