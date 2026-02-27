<?php

namespace NinjaPortal\Api\Tests\Feature;

use NinjaPortal\Api\Models\ActivityLog;
use NinjaPortal\Api\Listeners\PortalDomainActivitySubscriber;
use NinjaPortal\Api\Support\PortalApiContext;
use NinjaPortal\Api\Tests\TestCase;
use NinjaPortal\Portal\Events\Admin\AdminCreatedEvent;
use NinjaPortal\Portal\Models\Admin;

class PortalApiPackageTest extends TestCase
{
    public function test_context_resolves_configured_models_and_tables(): void
    {
        $context = app(PortalApiContext::class);

        $this->assertSame('portal_api_test_consumers', $context->consumerTable());
        $this->assertSame('portal_api_test_admins', $context->adminTable());
        $this->assertSame('api', $context->guardForContext('consumer'));
        $this->assertSame('admin', $context->guardForContext('admin'));
    }

    public function test_deleted_response_helper_defaults_to_http_200(): void
    {
        $response = response()->deleted('Deleted.');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_activity_logs_are_written_synchronously_by_default(): void
    {
        config()->set('portal-api.auth.admin_model', Admin::class);

        $admin = new Admin;
        $admin->forceFill([
            'id' => 1,
            'name' => 'Portal Owner',
            'email' => 'admin@example.com',
        ]);
        $admin->exists = true;

        auth()->guard('admin')->setUser($admin);

        event(new AdminCreatedEvent($admin));

        $this->assertDatabaseCount('portal_api_activity_logs', 1);

        $activity = ActivityLog::query()->firstOrFail();

        $this->assertSame('portal.admin.created', $activity->action);
        $this->assertSame('admin', $activity->actor_type);
        $this->assertSame('admin@example.com', $activity->actor_email);
        $this->assertSame('Admin', $activity->subject_type);
        $this->assertSame('1', $activity->subject_id);
    }

    public function test_activity_health_check_command_records_a_row(): void
    {
        $this->artisan('portal-api:activity:health-check', ['--cleanup' => false])
            ->assertSuccessful();

        $this->assertDatabaseCount('portal_api_activity_logs', 1);

        $activity = ActivityLog::query()->firstOrFail();

        $this->assertStringStartsWith('portal.activity.health_check.', $activity->action);
        $this->assertSame('admin', $activity->actor_type);
        $this->assertSame('activity.healthcheck@ninjaportal.test', $activity->actor_email);
        $this->assertSame('ActivityHealthCheck', $activity->subject_type);
    }

    public function test_all_subscribed_activity_events_resolve_valid_action_names(): void
    {
        $subscriber = app(PortalDomainActivitySubscriber::class);
        $reflection = new \ReflectionClass($subscriber);

        $subscriptionsMethod = $reflection->getMethod('subscriptions');
        $subscriptionsMethod->setAccessible(true);
        $eventBaseNameMethod = $reflection->getMethod('eventBaseName');
        $eventBaseNameMethod->setAccessible(true);
        $crudActionMethod = $reflection->getMethod('crudActionFromEventBase');
        $crudActionMethod->setAccessible(true);
        $syncActionMethod = $reflection->getMethod('syncActionFromEventBase');
        $syncActionMethod->setAccessible(true);
        $simpleActionMethod = $reflection->getMethod('simpleActionFromEventBase');
        $simpleActionMethod->setAccessible(true);

        /** @var array<class-string, string> $subscriptions */
        $subscriptions = $subscriptionsMethod->invoke($subscriber);

        foreach ($subscriptions as $eventClass => $handler) {
            $event = $this->makeSubscriberTestEvent($eventClass);
            $eventBase = $eventBaseNameMethod->invoke($subscriber, $event);

            $this->assertStringNotContainsString('Event', $eventBase, sprintf('%s base name should not retain the Event suffix.', $eventClass));
            $this->assertStringNotEndsWith('E', $eventBase, sprintf('%s base name was truncated incorrectly.', $eventClass));

            $action = match ($handler) {
                'handleCrud' => $crudActionMethod->invoke($subscriber, $eventBase),
                'handleSync' => $syncActionMethod->invoke($subscriber, $eventBase, $this->inferSubjectKeyForEventClass($eventClass)),
                'handleUserApp' => $crudActionMethod->invoke($subscriber, $eventBase) ?: $simpleActionMethod->invoke($subscriber, $eventBase),
                'handleCredential' => $simpleActionMethod->invoke($subscriber, $eventBase),
                default => null,
            };

            $this->assertNotNull(
                $action,
                sprintf('Failed to resolve an activity action for subscribed event %s using handler %s.', $eventClass, $handler)
            );
        }
    }

    protected function makeSubscriberTestEvent(string $eventClass): object
    {
        return (new \ReflectionClass($eventClass))->newInstanceWithoutConstructor();
    }

    protected function inferSubjectKeyForEventClass(string $eventClass): string
    {
        $base = class_basename($eventClass);
        $base = str_ends_with($base, 'Event') ? substr($base, 0, -5) : $base;
        $base = str_ends_with($base, 'Synced') ? substr($base, 0, -6) : $base;

        if (preg_match('/^(User|Audience|ApiProduct|Admin)/', $base, $matches) !== 1) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base) ?? $base);
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[1]) ?? $matches[1]);
    }
}
