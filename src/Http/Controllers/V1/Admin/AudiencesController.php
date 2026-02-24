<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreAudienceRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\SyncAudienceProductsRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\SyncAudienceUsersRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateAudienceRequest;
use NinjaPortal\Api\Http\Resources\AudienceResource;
use NinjaPortal\Portal\Contracts\Services\AudienceServiceInterface;
use NinjaPortal\Portal\Events\Audience\AudienceProductsSyncedEvent;
use NinjaPortal\Portal\Events\Audience\AudienceUsersSyncedEvent;
use NinjaPortal\Portal\Models\Audience;

/**
 * @group Admin: Audiences
 */
class AudiencesController extends Controller
{
    public function __construct(protected AudienceServiceInterface $audiences) {}

    /**
     * List audiences
     *
     * @authenticated
     *
     * @paginateRequest
     * @paginatedResponse
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\AudienceResource paginate=15
     * @apiResourceModel NinjaPortal\Portal\Models\Audience with=products
     */
    public function index(Request $request)
    {
        $this->authorizeCrudIndex(Audience::class);

        [$perPage, $orderBy, $direction] = $this->listParams($request, ['id', 'name', 'created_at']);

        $paginator = $this->audiences->paginate(
            perPage: $perPage,
            with: ['products'],
            orderBy: $orderBy,
            direction: $direction,
        );

        return $this->respondPaginated($request, $paginator, AudienceResource::class);
    }

    /**
     * Create audience
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\AudienceResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\Audience with=products
     */
    public function store(StoreAudienceRequest $request)
    {
        $this->authorizeCrudCreate(Audience::class);

        /** @var Audience $created */
        $created = $this->audiences->create($request->validated());
        $created->loadMissing('products');

        return $this->respondResource($request, new AudienceResource($created), 'Audience created.', status: 201);
    }

    /**
     * Get audience
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\AudienceResource
     * @apiResourceModel NinjaPortal\Portal\Models\Audience with=products,users
     */
    public function show(Request $request, Audience $audience)
    {
        $this->authorizeCrudView($audience);
        $audience->loadMissing(['products', 'users']);

        return $this->respondResource($request, new AudienceResource($audience));
    }

    /**
     * Update audience
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\AudienceResource
     * @apiResourceModel NinjaPortal\Portal\Models\Audience with=products
     */
    public function update(UpdateAudienceRequest $request, Audience $audience)
    {
        $this->authorizeCrudUpdate($audience);
        /** @var Audience $updated */
        $updated = $this->audiences->update($audience, $request->validated());
        $updated->loadMissing('products');

        return $this->respondResource($request, new AudienceResource($updated), 'Audience updated.');
    }

    /**
     * Delete audience
     *
     * @authenticated
     *
     * @response 200 null
     */
    public function destroy(Audience $audience)
    {
        $this->authorizeCrudDelete($audience);
        $this->audiences->delete($audience->getKey());

        return response()->success('Audience deleted.');
    }

    /**
     * Sync audience users
     *
     * @authenticated
     *
     * @bodyParam user_ids array required Example: [1,2,3]
     *
     * @response 200 {"message":"Audience users synced."}
     */
    public function syncUsers(SyncAudienceUsersRequest $request, Audience $audience)
    {
        $this->authorizeCrudUpdate($audience);
        $ids = $request->validated()['user_ids'];
        $audience->users()->sync($ids);
        AudienceUsersSyncedEvent::dispatch($audience, $ids);

        return response()->success('Audience users synced.');
    }

    /**
     * Sync audience products
     *
     * @authenticated
     *
     * @bodyParam api_product_ids array required Example: [1,2,3]
     *
     * @response 200 {"message":"Audience products synced."}
     */
    public function syncProducts(SyncAudienceProductsRequest $request, Audience $audience)
    {
        $this->authorizeCrudUpdate($audience);
        $ids = $request->validated()['api_product_ids'];
        $audience->products()->sync($ids);
        AudienceProductsSyncedEvent::dispatch($audience, $ids);

        return response()->success('Audience products synced.');
    }
}
