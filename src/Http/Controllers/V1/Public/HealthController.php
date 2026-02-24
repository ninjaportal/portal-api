<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Public;

use NinjaPortal\Api\Http\Controllers\Controller;

/**
 * @group Health
 */
class HealthController extends Controller
{
    /**
     * Health check
     */
    public function __invoke()
    {
        return response()->success('OK', ['status' => 'ok']);
    }
}
