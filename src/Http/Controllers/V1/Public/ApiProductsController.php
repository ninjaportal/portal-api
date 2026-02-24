<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Public;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\ApiProductResource;
use NinjaPortal\Portal\Contracts\Services\ApiProductServiceInterface;
use NinjaPortal\Portal\Models\ApiProduct;

/**
 * @group API Products
 */
class ApiProductsController extends Controller
{
    public function __construct(protected ApiProductServiceInterface $products) {}

    /**
     * List API products
     *
     * @queryParam visibility string Filter by visibility. Example: public
     * @queryParam scope string Use `mine` to return products visible to the authenticated user's audiences. Requires authentication.
     * @paginateRequest
     *
     * @paginatedResponse
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\ApiProductResource paginate=15
     * @apiResourceModel NinjaPortal\Portal\Models\ApiProduct with=translations,categories,categories.translations,audiences
     */
    public function index(Request $request)
    {
        [$perPage, $orderBy, $direction] = $this->listParams(
            $request,
            ['id', 'slug', 'created_at', 'updated_at'],
            'id'
        );

        $visibility = strtolower((string) $request->query('visibility', 'public'));
        $scope = strtolower((string) $request->query('scope', ''));
        $guard = (string) config('portal-api.auth.guards.consumer', 'api');
        $auth = auth($guard);

        if ($scope === 'mine') {
            if (! $auth->check()) {
                return response()->unauthorized('Unauthenticated.');
            }
        }

        $userAudienceIds = $auth->user()?->audiences?->pluck('id') ?? collect();

        $paginator = $this->products->paginate(
            perPage: $perPage,
            with: ['translations', 'categories', 'categories.translations', 'audiences'],
            orderBy: $orderBy,
            direction: $direction,
            extendQuery: function ($query) use ($scope, $userAudienceIds, $visibility) {
                if ($scope === 'mine') {
                    $query->where(function ($query) use ($userAudienceIds) {
                        $query->whereHas('audiences', function ($query) use ($userAudienceIds) {
                            $query->whereIn('audience_id', $userAudienceIds);
                        })->orWhereDoesntHave('audiences');
                    });
                }

                if (in_array($visibility, ['public', 'private', 'draft'], true)) {
                    $query->where('visibility', $visibility);
                } else {
                    $query->where('visibility', 'public');
                }

                return $query;
            }
        );

        return $this->respondPaginated($request, $paginator, ApiProductResource::class);
    }

    /**
     * Get API product
     *
     * @apiResource NinjaPortal\Api\Http\Resources\ApiProductResource
     * @apiResourceModel NinjaPortal\Portal\Models\ApiProduct with=translations,categories,categories.translations,audiences
     */
    public function show(Request $request, ApiProduct $apiProduct)
    {
        if ($apiProduct->visibility !== 'public') {
            $guard = (string) config('portal-api.auth.guards.consumer', 'api');
            $auth = auth($guard);
            if (! $auth->check()) {
                return response()->unauthorized('Unauthenticated.');
            }

            $userAudienceIds = $auth->user()->audiences?->pluck('id') ?? collect();
            $allowed = $apiProduct->audiences()->whereIn('audience_id', $userAudienceIds)->exists()
                || ! $apiProduct->audiences()->exists();

            if (! $allowed) {
                return response()->notFound('Not found.');
            }
        }

        $apiProduct->loadMissing(['translations', 'categories', 'categories.translations', 'audiences']);

        return $this->respondResource($request, new ApiProductResource($apiProduct));
    }
}
