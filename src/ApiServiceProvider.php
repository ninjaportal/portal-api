<?php

namespace NinjaPortal\Api;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use NinjaPortal\Api\Auth\AuthFlow;
use NinjaPortal\Api\Authorization\Console\Commands\ActivityHealthCheckCommand;
use NinjaPortal\Api\Authorization\Console\Commands\PruneRefreshTokensCommand;
use NinjaPortal\Api\Authorization\PolicyApiAuthorizer;
use NinjaPortal\Api\Contracts\Auth\AuthFlowInterface;
use NinjaPortal\Api\Contracts\Auth\TokenServiceInterface;
use NinjaPortal\Api\Contracts\Authorization\ApiAuthorizerInterface;
use NinjaPortal\Api\Http\Mixins\ApiRequestMixin;
use NinjaPortal\Api\Http\Mixins\ApiResponseMixin;
use NinjaPortal\Api\Jobs\PruneRefreshTokensJob;
use NinjaPortal\Api\Listeners\PortalDomainActivitySubscriber;
use NinjaPortal\Api\Services\RefreshTokenPruner;
use NinjaPortal\Api\Services\TokenService;
use NinjaPortal\Api\Support\PortalApiContext;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ApiServiceProvider extends PackageServiceProvider
{
    protected static bool $activitySubscriberRegistered = false;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('portal-api')
            ->hasConfigFile('portal-api')
            ->hasMigrations([
                '2026_01_15_000000_create_portal_api_refresh_tokens_table',
                '2026_01_16_000000_create_portal_api_activity_logs_table',
                '2026_01_17_000000_seed_portal_api_admin_rbac_guard',
            ])
            ->hasCommands([
                PruneRefreshTokensCommand::class,
                ActivityHealthCheckCommand::class,
            ])
            ->hasRoutes('api');
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->registerCoreBindings();
        $this->extendAuthConfig();
        $this->extendJwtConfig();
        $this->registerPruneSchedule();

        Request::mixin(new ApiRequestMixin);
        Response::mixin(new ApiResponseMixin);

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', \Spatie\Permission\Middleware\RoleMiddleware::class);
        $router->aliasMiddleware('permission', \Spatie\Permission\Middleware\PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class);

        if (! self::$activitySubscriberRegistered) {
            Event::subscribe(PortalDomainActivitySubscriber::class);
            self::$activitySubscriberRegistered = true;
        }
    }

    protected function registerCoreBindings(): void
    {
        $this->app->singleton(PortalApiContext::class);
        $this->app->bind(ApiAuthorizerInterface::class, PolicyApiAuthorizer::class);
        $this->app->singleton(RefreshTokenPruner::class);
        $this->app->bind(TokenServiceInterface::class, TokenService::class);
        $this->app->bind(AuthFlowInterface::class, AuthFlow::class);
    }

    protected function registerPruneSchedule(): void
    {
        $enabled = (bool) config('portal-api.tokens.prune.enabled', false);
        if (! $enabled) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $useQueue = (bool) config('portal-api.tokens.prune.queue', false);
            $frequency = strtolower((string) config('portal-api.tokens.prune.frequency', 'daily'));
            $time = (string) config('portal-api.tokens.prune.time', '03:00');

            if ($useQueue) {
                $event = $schedule->job(new PruneRefreshTokensJob);
            } else {
                $event = $schedule->command('portal-api:tokens:prune');
            }

            $event->name('portal-api:refresh-token-prune')->withoutOverlapping();

            if ($frequency === 'hourly') {
                $event->hourly();

                return;
            }

            $event->dailyAt($time !== '' ? $time : '03:00');
        });
    }

    /**
     * Extend auth config to register JWT guards for consumer and admin contexts.
     * Uses config keys to reference models from the portal package.
     */
    protected function extendAuthConfig(): void
    {
        // Resolve models from portal-api config (which reads from ninjaportal config)
        $consumerModel = (string) config('portal-api.auth.consumer_model');
        $adminModel = (string) config('portal-api.auth.admin_model');

        // Resolve guard names from config
        $consumerGuard = (string) config('portal-api.auth.guards.consumer', 'api');
        $adminGuard = (string) config('portal-api.auth.guards.admin', 'admin');

        config([
            // Consumer JWT guard/provider
            "auth.providers.{$consumerGuard}_provider" => [
                'driver' => 'eloquent',
                'model' => $consumerModel,
            ],
            "auth.guards.{$consumerGuard}" => [
                'driver' => 'jwt',
                'provider' => "{$consumerGuard}_provider",
            ],

            // Admin JWT guard/provider
            "auth.providers.{$adminGuard}_provider" => [
                'driver' => 'eloquent',
                'model' => $adminModel,
            ],
            "auth.guards.{$adminGuard}" => [
                'driver' => 'jwt',
                'provider' => "{$adminGuard}_provider",
            ],
        ]);
    }

    protected function extendJwtConfig(): void
    {
        $secret = config('jwt.secret');
        if (! is_string($secret) || trim($secret) === '') {
            config(['jwt.secret' => $this->resolveJwtSecret()]);
        }

        $ttl = (int) config('portal-api.tokens.access_ttl_minutes', 15);
        if ($ttl > 0) {
            config(['jwt.ttl' => $ttl]);
        }
    }

    protected function resolveJwtSecret(): string
    {
        $secret = config('portal-api.tokens.jwt_secret');
        if (is_string($secret) && trim($secret) !== '') {
            return $secret;
        }

        $appKey = (string) config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $appKey;
    }
}
