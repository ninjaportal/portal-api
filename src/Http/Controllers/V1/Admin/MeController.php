<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\UserResource;

/**
 * @group Me (Admin)
 */
class MeController extends Controller
{
    /**
     * Get current admin
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\UserResource
     * @apiResourceModel NinjaPortal\Portal\Models\Admin
     */
    public function __invoke()
    {
        return response()->success(new UserResource(auth()->user()));
    }
}
