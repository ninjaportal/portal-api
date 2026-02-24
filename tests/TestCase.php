<?php

namespace NinjaPortal\Api\Tests;

use NinjaPortal\Api\ApiServiceProvider;
use NinjaPortal\Api\Tests\Fixtures\TestAdmin;
use NinjaPortal\Api\Tests\Fixtures\TestConsumer;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ApiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('portal-api.auth.consumer_model', TestConsumer::class);
        $app['config']->set('portal-api.auth.admin_model', TestAdmin::class);
        $app['config']->set('portal-api.auth.guards.consumer', 'api');
        $app['config']->set('portal-api.auth.guards.admin', 'portal_api_admin');
        $app['config']->set('portal-api.authorization.use_policies', false);
        $app['config']->set('portal-api.rbac.enabled', false);
    }
}
