# NinjaPortal API Package

REST API package for NinjaPortal (`ninjaportal/portal`) with:
- Versioned routes (`/api/v1/...`)
- Admin + consumer auth flows (JWT access token + DB refresh token)
- Admin RBAC (Spatie permissions)
- Consistent response envelope + API exception normalization
- Activity logging subscriber for Portal domain events

## Routes
- Default base path: `api/v1`
- Admin sub-prefix: `admin` (full admin base path: `api/v1/admin`)

## Configuration Highlights
Main config: `config/portal-api.php`

Important keys:
- `portal-api.prefix`
- `portal-api.admin_prefix`
- `portal-api.auth.consumer_model`
- `portal-api.auth.admin_model`
- `portal-api.auth.guards.*`
- `portal-api.rbac.enabled`
- `portal-api.authorization.use_policies`
- `portal-api.tokens.*`
- `portal-api.tokens.prune.*`

## Extensibility (Client Customization)

### 1) Custom User/Admin Models
The package supports overriding the consumer/admin auth models via config:
- `PORTAL_API_CONSUMER_MODEL`
- `PORTAL_API_ADMIN_MODEL`

The API package now resolves model classes and table names through `NinjaPortal\Api\Support\PortalApiContext`, which is used by:
- auth flow (login)
- route-adjacent user resolution in admin user endpoints
- request validation rules that previously hardcoded `users` table

### 2) Authentication Plugins (MFA / SSO)
Auth controllers now depend on `NinjaPortal\Api\Contracts\Auth\AuthFlowInterface` instead of directly implementing login logic.

Default implementation:
- `NinjaPortal\Api\Auth\AuthFlow`

Token lifecycle is abstracted by:
- `NinjaPortal\Api\Contracts\Auth\TokenServiceInterface`
- default: `NinjaPortal\Api\Services\TokenService`

This makes it easier for a separate package to:
- replace the auth flow (SSO-first login, MFA challenge flow)
- decorate token issuing/refresh/revoke behavior
- listen to auth lifecycle events

### 3) Auth Lifecycle Events (for plugins)
Events emitted by the API package:
- `NinjaPortal\Api\Events\Auth\LoginAttemptedEvent`
- `NinjaPortal\Api\Events\Auth\LoginFailedEvent`
- `NinjaPortal\Api\Events\Auth\LoginSucceededEvent`
- `NinjaPortal\Api\Events\Auth\TokenIssuedEvent`
- `NinjaPortal\Api\Events\Auth\TokenRefreshedEvent`
- `NinjaPortal\Api\Events\Auth\TokenRevokedEvent`

Suggested MFA package approach:
1. Bind your own `AuthFlowInterface` implementation.
2. Reuse `TokenServiceInterface` after challenge success.
3. Optionally listen to auth events for auditing / throttling / risk scoring.

### 4) Authorization Strategy Replacement
Admin controllers can optionally perform policy checks through:
- `NinjaPortal\Api\Contracts\Authorization\ApiAuthorizerInterface`
- default implementation: `NinjaPortal\Api\Authorization\PolicyApiAuthorizer`

Disable policy checks (keep route RBAC only):
- `PORTAL_API_USE_POLICIES=false`

Replace the authorizer in another package/service provider if you need:
- ABAC
- tenant-aware checks
- external policy engines

## RBAC vs Policy Checks
Two layers can coexist:
- Route RBAC (Spatie): controlled by `PORTAL_API_RBAC_ENABLED`
- Controller policy checks: controlled by `PORTAL_API_USE_POLICIES`

Recommended for standalone portal deployments:
- Keep both enabled (`true`)

## Pagination / List Endpoint Conventions
Shared helpers live in:
- `src/Http/Controllers/Controller.php`
- `src/Http/Mixins/ApiRequestMixin.php`

Preferred list pattern:
1. Use `listParams()` for `per_page`, `order_by`, `direction`
2. Query/paginate in service or query builder
3. Return via `respondPaginated()`

### Intentionally Unpaginated Endpoints (Current Design)
These are intentionally returned as full collections because they are typically small/config-like datasets:
- Admin settings index
- Admin menus index
- Admin menu items index (per menu)
- RBAC roles index
- RBAC permissions index

If a client deployment grows these significantly, convert them to the standard paginated pattern.

## Refresh Token Pruning
Refresh tokens are stored in `portal_api_refresh_tokens`.

Prune expired/revoked rows manually:
```bash
php artisan portal-api:tokens:prune
```

Queue prune job instead:
```bash
php artisan portal-api:tokens:prune --queue
```

Optional scheduler integration (package-level):
- `PORTAL_API_REFRESH_PRUNE_ENABLED=true`
- `PORTAL_API_REFRESH_PRUNE_QUEUE=false`
- `PORTAL_API_REFRESH_PRUNE_FREQUENCY=daily` (`daily` or `hourly`)
- `PORTAL_API_REFRESH_PRUNE_TIME=03:00` (used for `daily`)

## Docs (Scribe)
- Generate docs: `php artisan scribe:generate`
- Docs endpoint (if enabled in `config/scribe.php`): `/docs`

## Development (Package-local)
Available scripts:
- `composer test`
- `composer analyse`
- `composer format`

Note:
- In this monorepo, package-local installs may need Composer path repositories for local package dependencies (`ninjaportal/portal`, etc.).
