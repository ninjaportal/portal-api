<?php

return [
    /*
     * Base API route prefix.
     */
    'prefix' => env('PORTAL_API_PREFIX', 'api/v1'),

    /*
     * Sub-prefix for admin-facing endpoints (used by the admin frontend).
     * Full base path becomes: `{prefix}/{admin_prefix}`.
     */
    'admin_prefix' => env('PORTAL_API_ADMIN_PREFIX', 'admin'),

    /*
     * Middleware applied to all API routes provided by this package.
     */
    'middleware' => [
        'api',
        \NinjaPortal\Api\Http\Middleware\ForceJsonResponse::class,
        \NinjaPortal\Api\Http\Middleware\ApiExceptionMiddleware::class,
    ],

    /*
     * Authentication models (defaults are resolved from the Portal config).
     *
     * - consumer: portal users/developers
     * - admin: dashboard/admin users
     */
    'auth' => [
        'consumer_model' => env('PORTAL_API_CONSUMER_MODEL') ?: (config('ninjaportal.models.User') ?: \App\Models\User::class),
        'admin_model' => env('PORTAL_API_ADMIN_MODEL') ?: (config('ninjaportal.models.Admin') ?: (config('ninjaportal.models.User') ?: \App\Models\User::class)),
        'guards' => [
            'consumer' => env('PORTAL_API_CONSUMER_GUARD', 'api'),
            'admin' => env('PORTAL_API_ADMIN_GUARD', 'admin'),
        ],
    ],

    /*
     * Token settings (JWT access token + opaque refresh token).
     */
    'tokens' => [
        'access_ttl_minutes' => (int) env('PORTAL_API_ACCESS_TTL', 15),
        'refresh_ttl_days' => (int) env('PORTAL_API_REFRESH_TTL', 30),
        'jwt_secret' => env('PORTAL_API_JWT_SECRET'), // defaults to APP_KEY when null
        'prune' => [
            'enabled' => env('PORTAL_API_REFRESH_PRUNE_ENABLED', false),
            'queue' => env('PORTAL_API_REFRESH_PRUNE_QUEUE', false),
            'frequency' => env('PORTAL_API_REFRESH_PRUNE_FREQUENCY', 'daily'), // daily|hourly
            'time' => env('PORTAL_API_REFRESH_PRUNE_TIME', '03:00'),
        ],
    ],

    /*
     * Pagination defaults.
     */
    'pagination' => [
        'per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
     * Activity logging (audit trail) for critical API actions.
     */
    'activity' => [
        'enabled' => env('PORTAL_API_ACTIVITY_ENABLED', true),
        'queue' => env('PORTAL_API_ACTIVITY_QUEUE', false),
    ],

    /*
     * RBAC enforcement for admin endpoints (Spatie roles/permissions).
     */
    'rbac' => [
        'enabled' => env('PORTAL_API_RBAC_ENABLED', true),

        /*
         * Roles that bypass permission checks (useful for a super admin).
         */
        'bypass_roles' => array_values(array_filter(explode(',', (string) env('PORTAL_API_RBAC_BYPASS_ROLES', 'super_admin')))),

        /*
         * Permissions required by the API middleware.
         */
        'permissions' => [
            'access_admin' => env('PORTAL_API_RBAC_ACCESS_ADMIN_PERMISSION', 'portal.admin.access'),
            'manage_rbac' => env('PORTAL_API_RBAC_MANAGE_RBAC_PERMISSION', 'portal.rbac.manage'),
            'manage_admins' => env('PORTAL_API_RBAC_MANAGE_ADMINS_PERMISSION', 'portal.admins.manage'),
            'view_activities' => env('PORTAL_API_RBAC_VIEW_ACTIVITIES_PERMISSION', 'portal.activities.view'),
        ],
    ],

    /*
     * Optional policy-based authorization checks in controllers.
     * Keep enabled for standalone portal deployments. Can be replaced by binding
     * NinjaPortal\Api\Contracts\Authorization\ApiAuthorizerInterface.
     */
    'authorization' => [
        'use_policies' => env('PORTAL_API_USE_POLICIES', true),
    ],

    /*
     * Settings exposed by the public config endpoint.
     */
    'public_settings' => [
        'groups' => [
            'Portal',
            'Branding',
            'Feature Flags',
        ],
        'keys' => [],
    ],
];
