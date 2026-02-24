<?php

namespace NinjaPortal\Api\Http\Controllers\V1\User;

use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Requests\V1\User\UpdatePasswordRequest;
use NinjaPortal\Api\Http\Requests\V1\User\UpdateProfileRequest;
use NinjaPortal\Api\Http\Resources\UserResource;
use NinjaPortal\Portal\Contracts\Services\UserServiceInterface;

/**
 * @group Me (Consumer)
 */
class MeController extends Controller
{
    public function __construct(protected UserServiceInterface $users) {}

    /**
     * Get current user
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\UserResource
     * @apiResourceModel NinjaPortal\Portal\Models\User
     */
    public function __invoke()
    {
        return response()->success(new UserResource(auth()->user()));
    }

    /**
     * Update profile
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\UserResource
     * @apiResourceModel NinjaPortal\Portal\Models\User
     */
    public function update(UpdateProfileRequest $request)
    {
        $data = $this->normalizeNameFields($request->validated());
        $user = $this->users->update(auth()->user(), $data);

        return response()->success(new UserResource($user));
    }

    /**
     * Update password
     *
     * @authenticated
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $data = $request->validated();

        try {
            $this->users->updatePassword(auth()->id(), $data['current_password'], $data['password']);
        } catch (\Throwable $e) {
            return response()->errors($e->getMessage(), [], 422);
        }

        return response()->success('Password updated.');
    }

    protected function normalizeNameFields(array $data): array
    {
        $hasParts = array_key_exists('first_name', $data) || array_key_exists('last_name', $data);
        if ($hasParts) {
            return $data;
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $data;
        }

        $segments = preg_split('/\s+/', $name);
        $data['first_name'] = $segments[0] ?? $name;
        $data['last_name'] = count($segments) > 1 ? implode(' ', array_slice($segments, 1)) : '';
        unset($data['name']);

        return $data;
    }
}
