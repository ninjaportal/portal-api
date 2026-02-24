<?php

namespace NinjaPortal\Api\Http\Mixins;

use Closure;

/**
 * Request helpers for common API query params.
 *
 * Adds macros to {@see \Illuminate\Http\Request} (Macroable), so they can be called
 * as instance methods (`$request->preRequest()`) or statically (`Request::preRequest()`).
 *
 * @method static array{order_by: string, direction: string, per_page: int, with: array<int, string>, filters: array} preRequest(?string $option = null)
 * @method array{order_by: string, direction: string, per_page: int, with: array<int, string>, filters: array} preRequest(?string $option = null)
 *
 * @mixin \Illuminate\Http\Request
 */
class ApiRequestMixin
{
    /**
     * Parse list/pagination query params with sensible defaults.
     *
     * Supported aliases (for backwards compatibility):
     * - order_by: order_by | sortBy | sort
     * - direction: direction | sortDir
     *
     * @return Closure(?string): array<string, mixed>|mixed
     */
    public function preRequest(): Closure
    {
        return function (?string $option = null) {
            $request = request();

            $perPageDefault = (int) config('portal-api.pagination.per_page', 15);
            $perPageMax = (int) config('portal-api.pagination.max_per_page', 100);

            $orderBy = $request->query(
                'order_by',
                $request->query('sortBy', $request->query('sort', 'id'))
            );

            $direction = $request->query(
                'direction',
                $request->query('sortDir', 'DESC')
            );

            $with = $request->query('with', '');
            $with = is_string($with) ? $with : '';

            $payload = [
                'order_by' => is_string($orderBy) && $orderBy !== '' ? $orderBy : 'id',
                'direction' => is_string($direction) && $direction !== '' ? strtoupper($direction) : 'DESC',
                'per_page' => max(1, min((int) $request->query('per_page', $perPageDefault), $perPageMax)),
                'with' => array_values(array_filter(array_map('trim', explode(',', $with)))),
                'filters' => json_decode((string) $request->query('filters', '[]'), true) ?: [],
            ];

            if ($option !== null) {
                return $payload[$option] ?? null;
            }

            return $payload;
        };
    }
}
