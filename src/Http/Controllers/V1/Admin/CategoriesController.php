<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\CategoryResource;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreCategoryRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateCategoryRequest;
use NinjaPortal\Portal\Contracts\Services\CategoryServiceInterface;
use NinjaPortal\Portal\Models\Category;

/**
 * @group Admin: Categories
 */
class CategoriesController extends Controller
{
    public function __construct(protected CategoryServiceInterface $categories) {}

    /**
     * List categories
     *
     * @authenticated
     *
     * @paginateRequest
     * @paginatedResponse
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\CategoryResource paginate=15
     * @apiResourceModel NinjaPortal\Portal\Models\Category with=translations
     */
    public function index(Request $request)
    {
        $this->authorizeCrudIndex(Category::class);

        [$perPage, $orderBy, $direction, $extendQuery] = $this->listParams(
            $request,
            ['id', 'slug', 'created_at'],
            'id',
            ['name' => 'name']
        );

        $paginator = $this->categories->paginate(
            perPage: $perPage,
            with: ['translations'],
            orderBy: $orderBy,
            direction: $direction,
            extendQuery: $extendQuery,
        );

        return $this->respondPaginated($request, $paginator, CategoryResource::class);
    }

    /**
     * Create category
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\CategoryResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\Category with=translations
     */
    public function store(StoreCategoryRequest $request)
    {
        $this->authorizeCrudCreate(Category::class);

        $data = $this->handleTranslatableUploads(
            $request->validated(),
            ['thumbnail'],
            'portal/categories'
        );

        /** @var Category $created */
        $created = $this->categories->create($data);
        $created->loadMissing('translations');

        return $this->respondResource($request, new CategoryResource($created), 'Category created.', status: 201);
    }

    /**
     * Get category
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\CategoryResource
     * @apiResourceModel NinjaPortal\Portal\Models\Category with=translations
     */
    public function show(Request $request, Category $category)
    {
        $this->authorizeCrudView($category);
        $category->loadMissing('translations');

        return $this->respondResource($request, new CategoryResource($category));
    }

    /**
     * Update category
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\CategoryResource
     * @apiResourceModel NinjaPortal\Portal\Models\Category with=translations
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorizeCrudUpdate($category);
        $data = $this->handleTranslatableUploads(
            $request->validated(),
            ['thumbnail'],
            'portal/categories'
        );

        /** @var Category $updated */
        $updated = $this->categories->update($category, $data);
        $updated->loadMissing('translations');

        return $this->respondResource($request, new CategoryResource($updated), 'Category updated.');
    }

    /**
     * Delete category
     *
     * @authenticated
     *
     * @response 200 null
     */
    public function destroy(Category $category)
    {
        $this->authorizeCrudDelete($category);
        $this->categories->delete($category->getKey());

        return response()->success('Category deleted.');
    }
}
