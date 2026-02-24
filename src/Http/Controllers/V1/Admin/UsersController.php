<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreUserAppCredentialRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreUserAppRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\StoreUserRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateUserAppCredentialProductsRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateUserAppRequest;
use NinjaPortal\Api\Http\Requests\V1\Admin\UpdateUserRequest;
use NinjaPortal\Api\Http\Resources\PortalUserResource;
use NinjaPortal\Api\Support\PortalApiContext;
use NinjaPortal\Portal\Contracts\Services\UserAppCredentialServiceInterface;
use NinjaPortal\Portal\Contracts\Services\UserAppServiceInterface;
use NinjaPortal\Portal\Contracts\Services\UserServiceInterface;
use NinjaPortal\Portal\Events\User\UserAudiencesSyncedEvent;

/**
 * @group Admin: Users
 */
class UsersController extends Controller
{
    public function __construct(
        protected UserServiceInterface $users,
        protected UserAppServiceInterface $apps,
        protected UserAppCredentialServiceInterface $credentials,
        protected PortalApiContext $apiContext
    ) {}

    /**
     * List users
     *
     * @authenticated
     *
     * @queryParam search string Example: youssef
     * @queryParam status string Example: pending
     * @paginateRequest
     *
     * @paginatedResponse
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\PortalUserResource paginate=15
     * @apiResourceModel NinjaPortal\Portal\Models\User with=audiences
     */
    public function index(Request $request)
    {
        $this->authorizeCrudIndex($this->apiContext->consumerModelClass());

        [$perPage, $orderBy, $direction] = $this->listParams($request, [
            'id',
            'email',
            'first_name',
            'last_name',
            'status',
            'created_at',
            'updated_at',
        ]);

        $paginator = $this->users->paginate(
            perPage: $perPage,
            with: ['audiences'],
            orderBy: $orderBy,
            direction: $direction,
        );

        return $this->respondPaginated($request, $paginator, PortalUserResource::class);
    }

    /**
     * Create user
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\PortalUserResource status=201
     * @apiResourceModel NinjaPortal\Portal\Models\User with=audiences
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorizeCrudCreate($this->apiContext->consumerModelClass());

        $data = $request->validated();

        $audienceIds = $data['audience_ids'] ?? null;
        unset($data['audience_ids']);

        /** @var Model $user */
        $user = $this->users->create($data);

        if (is_array($audienceIds)) {
            $user->audiences()->sync($audienceIds);
            $user->load('audiences');
            UserAudiencesSyncedEvent::dispatch($user, $audienceIds);
        }

        return $this->respondResource($request, new PortalUserResource($user), 'User created.', status: 201);
    }

    /**
     * Get user
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\PortalUserResource
     * @apiResourceModel NinjaPortal\Portal\Models\User with=audiences
     */
    public function show(Request $request, mixed $user)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudView($user);
        $user->loadMissing('audiences');

        return $this->respondResource($request, new PortalUserResource($user));
    }

    /**
     * Update user
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\PortalUserResource
     * @apiResourceModel NinjaPortal\Portal\Models\User with=audiences
     */
    public function update(UpdateUserRequest $request, mixed $user)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $data = $request->validated();

        $audienceIds = $data['audience_ids'] ?? null;
        unset($data['audience_ids']);

        /** @var Model $updated */
        $updated = $this->users->update($user, $data);

        if (is_array($audienceIds)) {
            $updated->audiences()->sync($audienceIds);
            UserAudiencesSyncedEvent::dispatch($updated, $audienceIds);
        }

        $updated->loadMissing('audiences');

        return $this->respondResource($request, new PortalUserResource($updated), 'User updated.');
    }

    /**
     * Delete user
     *
     * @authenticated
     *
     * @response 200 null
     */
    public function destroy(mixed $user)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudDelete($user);
        $this->users->delete($user->getKey());

        return response()->success('User deleted.');
    }

    /**
     * List a user's Apigee apps
     *
     * @authenticated
     *
     * @response 200 [{"name":"My App","status":"approved"}]
     */
    public function apps(mixed $user)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudView($user);
        return response()->success($this->apps->all((string) $user->email));
    }

    /**
     * Create an Apigee app for a user
     *
     * @authenticated
     *
     * @response 201 {"message":"App created.","name":"My App","status":"approved"}
     */
    public function createApp(StoreUserAppRequest $request, mixed $user)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $app = $this->apps->create((string) $user->email, $request->validated());

        return response()->created('App created.', $app);
    }

    /**
     * Delete an Apigee app for a user
     *
     * @authenticated
     *
     * @response 200 {"message":"App deleted."}
     */
    public function deleteApp(mixed $user, string $appName)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->apps->delete((string) $user->email, $appName);

        return response()->success('App deleted.');
    }

    /**
     * Update an Apigee app for a user
     *
     * @authenticated
     */
    public function updateApp(UpdateUserAppRequest $request, mixed $user, string $appName)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $app = $this->apps->update((string) $user->email, $appName, $request->validated());

        return response()->success('App updated.', $app);
    }

    /**
     * Approve an Apigee app for a user
     *
     * @authenticated
     */
    public function approveApp(mixed $user, string $appName)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $app = $this->apps->approve((string) $user->email, $appName);

        return response()->success('App approved.', $app);
    }

    /**
     * Revoke an Apigee app for a user
     *
     * @authenticated
     */
    public function revokeApp(mixed $user, string $appName)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $app = $this->apps->revoke((string) $user->email, $appName);

        return response()->success('App revoked.', $app);
    }

    /**
     * Create credentials for a user's app
     *
     * @authenticated
     */
    public function createCredential(StoreUserAppCredentialRequest $request, mixed $user, string $appName)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $payload = $request->validated();
        $this->credentials->create(
            (string) $user->email,
            $appName,
            $payload['api_products'],
            $payload['expires_in'] ?? null
        );

        return response()->created('Credential created.');
    }

    /**
     * Approve a credential by key
     *
     * @authenticated
     */
    public function approveCredential(mixed $user, string $appName, string $key)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->credentials->approve((string) $user->email, $appName, $key);

        return response()->success('Credential approved.');
    }

    /**
     * Revoke a credential by key
     *
     * @authenticated
     */
    public function revokeCredential(mixed $user, string $appName, string $key)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->credentials->revoke((string) $user->email, $appName, $key);

        return response()->success('Credential revoked.');
    }

    /**
     * Delete a credential by key
     *
     * @authenticated
     */
    public function deleteCredential(mixed $user, string $appName, string $key)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->credentials->delete((string) $user->email, $appName, $key);

        return response()->success('Credential deleted.');
    }

    /**
     * Add API products to a credential
     *
     * @authenticated
     */
    public function addCredentialProducts(UpdateUserAppCredentialProductsRequest $request, mixed $user, string $appName, string $key)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->credentials->addProducts((string) $user->email, $appName, $key, $request->validated()['api_products']);

        return response()->success('Credential products added.');
    }

    /**
     * Remove an API product from a credential
     *
     * @authenticated
     */
    public function removeCredentialProduct(mixed $user, string $appName, string $key, string $product)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->credentials->removeProducts((string) $user->email, $appName, $key, $product);

        return response()->success('Credential product removed.');
    }

    /**
     * Approve an API product on a credential
     *
     * @authenticated
     */
    public function approveCredentialProduct(mixed $user, string $appName, string $key, string $product)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->credentials->approveApiProduct((string) $user->email, $appName, $key, $product);

        return response()->success('Credential product approved.');
    }

    /**
     * Revoke an API product on a credential
     *
     * @authenticated
     */
    public function revokeCredentialProduct(mixed $user, string $appName, string $key, string $product)
    {
        $user = $this->resolveConsumerUser($user);
        $this->authorizeCrudUpdate($user);
        $this->credentials->revokeApiProduct((string) $user->email, $appName, $key, $product);

        return response()->success('Credential product revoked.');
    }

    protected function resolveConsumerUser(mixed $user): Model
    {
        if ($user instanceof Model) {
            return $user;
        }

        return $this->apiContext->findConsumerByRouteKeyOrFail($user);
    }
}
