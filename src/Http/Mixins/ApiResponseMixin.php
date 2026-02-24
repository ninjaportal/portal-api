<?php

namespace NinjaPortal\Api\Http\Mixins;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

/**
 * Standardized API JSON response helpers.
 *
 * @method static JsonResponse jsonResponse(bool $success, int $status, string $message, string $dataKey, mixed $data, array|null $meta = null)
 * @method static JsonResponse errors(string $message, array $errors = [], int $status = 400)
 * @method static JsonResponse success(string|array|object $message = '', array|object $data = [], int $status = 200)
 * @method static JsonResponse paginateResource(\Illuminate\Http\Resources\Json\ResourceCollection $resource)
 * @method static JsonResponse paginate(\Illuminate\Contracts\Pagination\LengthAwarePaginator $resource)
 * @method static JsonResponse created(string|array|object $message = '', array|object $data = [], int $status = 201)
 * @method static JsonResponse deleted(string $message = 'Item deleted successfully', array|object $data = [], int $status = 201)
 * @method static JsonResponse notFound(string $message = 'Not Found', int $status = 404)
 * @method static JsonResponse unauthorized(string $message = 'Unauthorized', int $status = 401)
 * @method static JsonResponse forbidden(string $message = 'Forbidden', int $status = 403)
 *
 * @mixin \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
 */
class ApiResponseMixin
{
    public function jsonResponse(): \Closure
    {
        return function (bool $success, int $status, string $message, string $dataKey, mixed $data, ?array $meta = null) {
            $payloadData = $data;
            if ($payloadData instanceof \Illuminate\Contracts\Support\Arrayable) {
                $payloadData = $payloadData->toArray();
            } elseif ($payloadData instanceof \JsonSerializable) {
                $payloadData = $payloadData->jsonSerialize();
            } elseif ($payloadData instanceof \Traversable) {
                $payloadData = iterator_to_array($payloadData);
            }

            if (is_array($payloadData)) {
                // Keep arrays intact even when empty
                $payloadData = $payloadData;
            } elseif ($payloadData === null || $payloadData === '') {
                $payloadData = new \stdClass;
            }

            return Response::json([
                'success' => $success,
                'status' => $status,
                'message' => $message,
                $dataKey => $payloadData,
                'meta' => $meta,
            ], $status);
        };
    }

    public function errors(): \Closure
    {
        return function ($message, $errors = [], $status = 400) {
            return $this->jsonResponse(false, $status, $message, 'errors', $errors);
        };
    }

    public function success(): \Closure
    {
        return function ($message = '', $data = [], $status = 200) {
            $data = empty($data) && (is_object($message) || is_array($message)) ? $message : $data;
            $message = is_string($message) ? $message : '';

            return $this->jsonResponse(true, $status, $message, 'data', $data);
        };
    }

    public function paginateResource(): \Closure
    {
        return function ($resource) {
            $paginator = $resource->resource;
            $resolved = $resource->resolve(request());
            $data = (is_array($resolved) && array_key_exists('data', $resolved) && is_array($resolved['data']))
                ? $resolved['data']
                : (is_array($resolved) ? $resolved : []);

            $meta = [
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'links' => [
                        'next' => $paginator->nextPageUrl(),
                        'prev' => $paginator->previousPageUrl(),
                    ],
                ],
            ];

            return $this->jsonResponse(true, 200, '', 'data', $data, $meta);
        };
    }

    public function paginate(): \Closure
    {
        return function ($resource) {
            $meta = [
                'pagination' => [
                    'total' => $resource->total(),
                    'count' => $resource->count(),
                    'per_page' => $resource->perPage(),
                    'current_page' => $resource->currentPage(),
                    'total_pages' => $resource->lastPage(),
                    'links' => [
                        'next' => $resource->nextPageUrl(),
                        'prev' => $resource->previousPageUrl(),
                    ],
                ],
            ];

            return $this->jsonResponse(true, 200, '', 'data', $resource->items(), $meta);
        };
    }

    public function created(): \Closure
    {
        return function ($message = '', $data = [], $status = 201) {
            return $this->success($message, $data, $status);
        };
    }

    public function deleted(): \Closure
    {
        return function ($message = 'Item deleted successfully', $data = [], $status = 200) {
            return $this->success($message, $data, $status);
        };
    }

    public function notFound(): \Closure
    {
        return function ($message = 'Not Found', $status = 404) {
            return $this->errors($message, [], $status);
        };
    }

    public function unauthorized(): \Closure
    {
        return function ($message = 'Unauthorized', $status = 401) {
            return $this->errors($message, [], $status);
        };
    }

    public function forbidden(): \Closure
    {
        return function ($message = 'Forbidden', $status = 403) {
            return $this->errors($message, [], $status);
        };
    }
}
