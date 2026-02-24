<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreApiProductRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateApiProductRequest;
use NinjaPortal\Api\Http\Resources\ApiProductResource;
use NinjaPortal\Portal\Contracts\Services\ApiProductServiceInterface;
use NinjaPortal\Portal\Events\ApiProduct\ApiProductAudiencesSyncedEvent;
use NinjaPortal\Portal\Events\ApiProduct\ApiProductCategoriesSyncedEvent;
use NinjaPortal\Portal\Models\ApiProduct;

/**
 * @group Admin: API Products
 */
class ApiProductsController extends Controller
{
    public function __construct(protected ApiProductServiceInterface $products) {}

    /**
     * List API products (catalog)
     *
     * @authenticated
     *
     * @paginateRequest
     * @paginatedResponse
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\ApiProductResource paginate=15
     * @apiResourceModel NinjaPortal\Portal\Models\ApiProduct with=translations,categories,categories.translations,audiences
     */
    public function index(Request $request)
    {
        $this->authorizeCrudIndex(ApiProduct::class);

        [$perPage, $orderBy, $direction, $extendQuery] = $this->listParams(
            $request,
            ['id', 'slug', 'visibility', 'apigee_product_id', 'created_at'],
            'id',
            ['name' => 'name']
        );

        $paginator = $this->products->paginate(
            perPage: $perPage,
            with: ['translations', 'categories', 'categories.translations', 'audiences'],
            orderBy: $orderBy,
            direction: $direction,
            extendQuery: $extendQuery
        );

        return $this->respondPaginated($request, $paginator, ApiProductResource::class);
    }

    /**
     * Create API product (catalog)
     *
     * Accepts translation payloads in either of these formats:
     * - direct: { "name": "...", "description": "..." } (uses current locale)
     * - locale keyed: { "en": { "name": "..." }, "ar": { "name": "..." } }
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\ApiProductResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\ApiProduct with=translations,categories,categories.translations,audiences
     */
    public function store(StoreApiProductRequest $request)
    {
        $this->authorizeCrudCreate(ApiProduct::class);

        $data = $this->handleTranslatableUploads(
            $request->validated(),
            ['thumbnail'],
            'portal/api-products'
        );
        $data = $this->handleFileFields($data);

        $categoryIds = $data['category_ids'] ?? null;
        $audienceIds = $data['audience_ids'] ?? null;
        unset($data['category_ids'], $data['audience_ids']);

        /** @var ApiProduct $created */
        $created = $this->products->create($data);

        if (is_array($categoryIds)) {
            $created->categories()->sync($categoryIds);
            ApiProductCategoriesSyncedEvent::dispatch($created, $categoryIds);
        }
        if (is_array($audienceIds)) {
            $created->audiences()->sync($audienceIds);
            ApiProductAudiencesSyncedEvent::dispatch($created, $audienceIds);
        }

        $created->loadMissing(['translations', 'categories', 'categories.translations', 'audiences']);

        return $this->respondResource($request, new ApiProductResource($created), 'API product created.', status: 201);
    }

    /**
     * Get API product (catalog)
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\ApiProductResource
     * @apiResourceModel NinjaPortal\Portal\Models\ApiProduct with=translations,categories,categories.translations,audiences
     */
    public function show(Request $request, ApiProduct $apiProduct)
    {
        $this->authorizeCrudView($apiProduct);
        $apiProduct->loadMissing(['translations', 'categories', 'categories.translations', 'audiences']);

        return $this->respondResource($request, new ApiProductResource($apiProduct));
    }

    /**
     * Update API product (catalog)
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\ApiProductResource
     * @apiResourceModel NinjaPortal\Portal\Models\ApiProduct with=translations,categories,categories.translations,audiences
     */
    public function update(UpdateApiProductRequest $request, ApiProduct $apiProduct)
    {
        $this->authorizeCrudUpdate($apiProduct);
        $data = $this->handleTranslatableUploads(
            $request->validated(),
            ['thumbnail'],
            'portal/api-products'
        );
        $data = $this->handleFileFields($data);

        $categoryIds = $data['category_ids'] ?? null;
        $audienceIds = $data['audience_ids'] ?? null;
        unset($data['category_ids'], $data['audience_ids']);

        /** @var ApiProduct $updated */
        $updated = $this->products->update($apiProduct, $data);

        if (is_array($categoryIds)) {
            $updated->categories()->sync($categoryIds);
            ApiProductCategoriesSyncedEvent::dispatch($updated, $categoryIds);
        }
        if (is_array($audienceIds)) {
            $updated->audiences()->sync($audienceIds);
            ApiProductAudiencesSyncedEvent::dispatch($updated, $audienceIds);
        }

        $updated->loadMissing(['translations', 'categories', 'categories.translations', 'audiences']);

        return $this->respondResource($request, new ApiProductResource($updated), 'API product updated.');
    }

    /**
     * Delete API product (catalog)
     *
     * @authenticated
     *
     * @response 200 null
     */
    public function destroy(ApiProduct $apiProduct)
    {
        $this->authorizeCrudDelete($apiProduct);
        $this->products->delete($apiProduct->getKey());

        return response()->success('API product deleted.');
    }

    /**
     * List Apigee API products (source of truth)
     *
     * @authenticated
     */
    public function apigeeIndex()
    {
        $this->authorizeCrudIndex(ApiProduct::class);

        return response()->success($this->products->apigeeProducts());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function handleFileFields(array $data): array
    {
        if (array_key_exists('swagger_url', $data)) {
            $data['swagger_url'] = $this->storeUploadedValue(
                $data['swagger_url'],
                'portal/api-products/swagger',
                ApiProduct::$STORAGE_DISK
            );
        }

        if (array_key_exists('integration_file', $data)) {
            $data['integration_file'] = $this->storeUploadedValue(
                $data['integration_file'],
                'portal/api-products/integrations',
                ApiProduct::$STORAGE_DISK
            );
        }

        return $data;
    }
}
