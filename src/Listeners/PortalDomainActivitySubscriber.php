<?php

namespace NinjaPortal\Api\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use NinjaPortal\Api\Jobs\RecordActivityJob;

class PortalDomainActivitySubscriber
{
    /**
     * Events we consider "critical actions".
     *
     * @return array<class-string, string>
     */
    protected function subscriptions(): array
    {
        return [
            // CRUD (Portal models)
            \NinjaPortal\Portal\Events\User\UserCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\User\UserUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\User\UserDeletedEvent::class => 'handleCrud',

            \NinjaPortal\Portal\Events\Admin\AdminCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Admin\AdminUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Admin\AdminDeletedEvent::class => 'handleCrud',

            \NinjaPortal\Portal\Events\ApiProduct\ApiProductCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\ApiProduct\ApiProductUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\ApiProduct\ApiProductDeletedEvent::class => 'handleCrud',

            \NinjaPortal\Portal\Events\Category\CategoryCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Category\CategoryUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Category\CategoryDeletedEvent::class => 'handleCrud',

            \NinjaPortal\Portal\Events\Audience\AudienceCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Audience\AudienceUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Audience\AudienceDeletedEvent::class => 'handleCrud',

            \NinjaPortal\Portal\Events\Menu\MenuCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Menu\MenuUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Menu\MenuDeletedEvent::class => 'handleCrud',

            \NinjaPortal\Portal\Events\Menu\MenuItemCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Menu\MenuItemUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Menu\MenuItemDeletedEvent::class => 'handleCrud',

            \NinjaPortal\Portal\Events\Setting\SettingCreatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Setting\SettingUpdatedEvent::class => 'handleCrud',
            \NinjaPortal\Portal\Events\Setting\SettingDeletedEvent::class => 'handleCrud',

            // Relationship syncs
            \NinjaPortal\Portal\Events\User\UserAudiencesSyncedEvent::class => 'handleSync',
            \NinjaPortal\Portal\Events\Audience\AudienceUsersSyncedEvent::class => 'handleSync',
            \NinjaPortal\Portal\Events\Audience\AudienceProductsSyncedEvent::class => 'handleSync',
            \NinjaPortal\Portal\Events\ApiProduct\ApiProductCategoriesSyncedEvent::class => 'handleSync',
            \NinjaPortal\Portal\Events\ApiProduct\ApiProductAudiencesSyncedEvent::class => 'handleSync',
            \NinjaPortal\Portal\Events\Admin\AdminRolesSyncedEvent::class => 'handleSync',

            // Apigee user apps
            \NinjaPortal\Portal\Events\UserApp\UserAppCreatedEvent::class => 'handleUserApp',
            \NinjaPortal\Portal\Events\UserApp\UserAppUpdatedEvent::class => 'handleUserApp',
            \NinjaPortal\Portal\Events\UserApp\UserAppDeletedEvent::class => 'handleUserApp',
            \NinjaPortal\Portal\Events\UserApp\UserAppApprovedEvent::class => 'handleUserApp',
            \NinjaPortal\Portal\Events\UserApp\UserAppRevokedEvent::class => 'handleUserApp',

            // Apigee credentials (canonical ...Event classes)
            \NinjaPortal\Portal\Events\UserAppCredentialCreatedEvent::class => 'handleCredential',
            \NinjaPortal\Portal\Events\UserAppCredentialApprovedEvent::class => 'handleCredential',
            \NinjaPortal\Portal\Events\UserAppCredentialRevokedEvent::class => 'handleCredential',
            \NinjaPortal\Portal\Events\UserAppCredentialDeletedEvent::class => 'handleCredential',
            \NinjaPortal\Portal\Events\UserAppCredentialProductAddedEvent::class => 'handleCredential',
            \NinjaPortal\Portal\Events\UserAppCredentialProductRemovedEvent::class => 'handleCredential',
            \NinjaPortal\Portal\Events\UserAppCredentialProductApprovedEvent::class => 'handleCredential',
            \NinjaPortal\Portal\Events\UserAppCredentialProductRevokedEvent::class => 'handleCredential',
        ];
    }

    public function subscribe(Dispatcher $events): void
    {
        foreach ($this->subscriptions() as $eventClass => $handler) {
            $events->listen($eventClass, [self::class, $handler]);
        }
    }

    public function handleCrud(object $event): void
    {
        if (! config('portal-api.activity.enabled', true)) {
            return;
        }

        $model = $this->extractFirstModel($event);
        if (! $model) {
            return;
        }

        $eventBase = $this->eventBaseName($event);
        $action = $this->crudActionFromEventBase($eventBase);
        if ($action === null) {
            return;
        }

        $this->record(
            action: sprintf('portal.%s.%s', $this->subjectKey($model), $action),
            subject: $model,
            properties: [
                'event' => get_class($event),
            ],
        );
    }

    public function handleSync(object $event): void
    {
        if (! config('portal-api.activity.enabled', true)) {
            return;
        }

        // Convention: a sync event has a primary model (first model property) and a list of ids.
        $model = $this->extractFirstModel($event);
        if (! $model) {
            return;
        }

        $eventBase = $this->eventBaseName($event);
        $action = $this->syncActionFromEventBase($eventBase, $this->subjectKey($model));
        if ($action === null) {
            return;
        }

        $ids = $this->extractFirstArrayProperty($event);

        $this->record(
            action: sprintf('portal.%s.%s', $this->subjectKey($model), $action),
            subject: $model,
            properties: array_filter([
                'event' => get_class($event),
                'ids' => $ids,
            ], static fn ($v) => $v !== null),
        );
    }

    public function handleUserApp(object $event): void
    {
        if (! config('portal-api.activity.enabled', true)) {
            return;
        }

        $eventBase = $this->eventBaseName($event);
        $action = $this->crudActionFromEventBase($eventBase) ?? $this->simpleActionFromEventBase($eventBase);
        if ($action === null) {
            return;
        }

        $app = $event->app ?? null;
        $email = $event->userEmail ?? null;

        $subjectId = null;
        $subjectLabel = null;
        if (is_object($app)) {
            if (method_exists($app, 'id')) {
                $subjectId = $app->id();
            }
            if (property_exists($app, 'name')) {
                $subjectLabel = (string) ($app->name ?? '');
            }
            if ($subjectId === null && $subjectLabel !== '') {
                $subjectId = $subjectLabel;
            }
        }

        $this->recordRaw(
            action: sprintf('portal.user_app.%s', $action),
            subjectType: 'UserApp',
            subjectId: is_string($subjectId) && $subjectId !== '' ? $subjectId : null,
            subjectLabel: is_string($subjectLabel) && $subjectLabel !== '' ? $subjectLabel : null,
            properties: array_filter([
                'event' => get_class($event),
                'user_email' => is_string($email) && $email !== '' ? $email : null,
            ], static fn ($v) => $v !== null),
        );
    }

    public function handleCredential(object $event): void
    {
        if (! config('portal-api.activity.enabled', true)) {
            return;
        }

        $eventBase = $this->eventBaseName($event);
        $action = $this->simpleActionFromEventBase($eventBase);
        if ($action === null) {
            return;
        }

        $email = $event->email ?? null;
        $appId = $event->appID ?? null;
        $credentialKey = $event->credentialKey ?? null;
        $apiProducts = $event->api_products ?? null;
        $apiProduct = $event->api_product ?? null;

        $this->recordRaw(
            action: sprintf('portal.user_app_credential.%s', $action),
            subjectType: 'UserAppCredential',
            subjectId: is_string($appId) && $appId !== '' ? $appId : null,
            subjectLabel: null,
            properties: array_filter([
                'event' => get_class($event),
                'user_email' => is_string($email) && $email !== '' ? $email : null,
                'credential_key' => is_string($credentialKey) && $credentialKey !== '' ? $credentialKey : null,
                'api_products' => is_array($apiProducts) ? $apiProducts : null,
                'api_product' => is_string($apiProduct) && $apiProduct !== '' ? $apiProduct : null,
            ], static fn ($v) => $v !== null),
        );
    }

    protected function record(string $action, Model $subject, array $properties = []): void
    {
        $this->recordRaw(
            action: $action,
            subjectType: class_basename($subject),
            subjectId: (string) $subject->getKey(),
            subjectLabel: $this->labelForModel($subject),
            properties: $properties,
        );
    }

    protected function recordRaw(
        string $action,
        string $subjectType,
        ?string $subjectId,
        ?string $subjectLabel,
        array $properties = []
    ): void {
        $request = request();
        $route = $request?->route();

        $actor = $this->resolveActor();

        $payload = [
            'action' => $action,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'actor_name' => $actor['name'],
            'actor_email' => $actor['email'],
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1024) : null,
            'properties' => array_filter(array_merge([
                'route' => $route?->getName(),
                'method' => $request?->method(),
                'uri' => $request?->path(),
            ], $properties), static fn ($v) => $v !== null),
        ];

        RecordActivityJob::dispatch($payload);
    }

    /**
     * @return array{type: string|null, id: int|string|null, name: string|null, email: string|null}
     */
    protected function resolveActor(): array
    {
        $adminGuard = (string) config('portal-api.auth.guards.admin', 'portal_api_admin');
        $consumerGuard = (string) config('portal-api.auth.guards.consumer', 'api');

        $user = Auth::user()
            ?? Auth::guard($adminGuard)->user()
            ?? Auth::guard($consumerGuard)->user();

        if (! $user) {
            return ['type' => null, 'id' => null, 'name' => null, 'email' => null];
        }

        $adminModel = (string) config('portal-api.auth.admin_model');
        $actorType = $adminModel !== '' && is_a($user, $adminModel) ? 'admin' : 'user';

        return [
            'type' => $actorType,
            'id' => method_exists($user, 'getKey') ? $user->getKey() : null,
            'name' => $user->name ?? $user->full_name ?? null,
            'email' => $user->email ?? null,
        ];
    }

    protected function extractFirstModel(object $event): ?Model
    {
        foreach (get_object_vars($event) as $value) {
            if ($value instanceof Model) {
                return $value;
            }
        }

        return null;
    }

    protected function extractFirstArrayProperty(object $event): ?array
    {
        foreach (get_object_vars($event) as $value) {
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function labelForModel(Model $model): ?string
    {
        foreach (['email', 'name', 'full_name', 'slug', 'key'] as $field) {
            if (isset($model->{$field}) && (string) $model->{$field} !== '') {
                return (string) $model->{$field};
            }
        }

        return null;
    }

    protected function subjectKey(Model $model): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($model)) ?? class_basename($model));
    }

    protected function eventBaseName(object $event): string
    {
        $base = class_basename($event);
        return str_ends_with($base, 'Event') ? substr($base, 0, -4) : $base;
    }

    protected function crudActionFromEventBase(string $eventBase): ?string
    {
        foreach (['Created' => 'created', 'Updated' => 'updated', 'Deleted' => 'deleted', 'Restored' => 'restored'] as $suffix => $action) {
            if (str_ends_with($eventBase, $suffix)) {
                return $action;
            }
        }

        return null;
    }

    protected function syncActionFromEventBase(string $eventBase, string $subjectKey): ?string
    {
        if (! str_ends_with($eventBase, 'Synced')) {
            return null;
        }

        $base = substr($eventBase, 0, -6);

        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base) ?? $base);

        // `ApiProductCategoriesSynced` -> `api_product_categories` -> `categories_synced`
        $prefix = rtrim($subjectKey, '_').'_';
        if (str_starts_with($snake, $prefix)) {
            $snake = substr($snake, strlen($prefix));
        }

        return $snake !== '' ? $snake.'_synced' : 'synced';
    }

    protected function simpleActionFromEventBase(string $eventBase): ?string
    {
        // Used for non-model events like UserAppCredentialProductAddedEvent
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $eventBase) ?? $eventBase);

        foreach ([
            'created' => '_created',
            'approved' => '_approved',
            'revoked' => '_revoked',
            'deleted' => '_deleted',
            'updated' => '_updated',
            'product_added' => '_product_added',
            'product_removed' => '_product_removed',
            'product_approved' => '_product_approved',
            'product_revoked' => '_product_revoked',
        ] as $action => $needle) {
            if (str_ends_with($snake, $needle)) {
                return $action;
            }
        }

        return null;
    }
}
