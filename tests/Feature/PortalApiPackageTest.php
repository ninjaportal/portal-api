<?php

namespace NinjaPortal\Api\Tests\Feature;

use NinjaPortal\Api\Support\PortalApiContext;
use NinjaPortal\Api\Tests\TestCase;

class PortalApiPackageTest extends TestCase
{
    public function test_context_resolves_configured_models_and_tables(): void
    {
        $context = app(PortalApiContext::class);

        $this->assertSame('portal_api_test_consumers', $context->consumerTable());
        $this->assertSame('portal_api_test_admins', $context->adminTable());
        $this->assertSame('api', $context->guardForContext('consumer'));
        $this->assertSame('portal_api_admin', $context->guardForContext('admin'));
    }

    public function test_deleted_response_helper_defaults_to_http_200(): void
    {
        $response = response()->deleted('Deleted.');

        $this->assertSame(200, $response->getStatusCode());
    }
}
