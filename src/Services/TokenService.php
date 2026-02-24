<?php

namespace NinjaPortal\Api\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use NinjaPortal\Api\Contracts\Auth\TokenServiceInterface;
use NinjaPortal\Api\Events\Auth\TokenIssuedEvent;
use NinjaPortal\Api\Events\Auth\TokenRefreshedEvent;
use NinjaPortal\Api\Events\Auth\TokenRevokedEvent;
use NinjaPortal\Api\Models\RefreshToken;
use NinjaPortal\Api\Support\PortalApiContext;

/**
 * Token service for issuing and refreshing JWT access tokens with opaque refresh tokens.
 */
class TokenService implements TokenServiceInterface
{
    public function __construct(protected PortalApiContext $context) {}

    /**
     * Issue a new token pair (access + refresh) for the given user.
     *
     * @param  Authenticatable  $user  The user to issue tokens for
     * @param  string  $context  Either 'admin' or 'consumer'
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string}
     */
    public function issue(Authenticatable $user, string $context): array
    {
        $guard = $this->resolveGuardName($context);
        $accessTtlMinutes = (int) config('portal-api.tokens.access_ttl_minutes', 15);
        $refreshTtlDays = (int) config('portal-api.tokens.refresh_ttl_days', 30);

        // Use auth() helper with the resolved guard
        $authGuard = auth($guard);
        if (method_exists($authGuard, 'setTTL')) {
            $authGuard->setTTL($accessTtlMinutes);
        }

        $accessToken = $authGuard->login($user);

        // Generate opaque refresh token
        $refreshPlain = bin2hex(random_bytes(32));
        $refreshHash = hash('sha256', $refreshPlain);

        RefreshToken::query()->create([
            'context' => $context,
            'token_hash' => $refreshHash,
            'tokenable_type' => $user::class,
            'tokenable_id' => (int) $user->getAuthIdentifier(),
            'expires_at' => Carbon::now()->addDays($refreshTtlDays),
        ]);

        $payload = [
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'expires_in' => $accessTtlMinutes * 60,
            'refresh_token' => $refreshPlain,
        ];

        Event::dispatch(new TokenIssuedEvent($context, $user, $payload));

        return $payload;
    }

    /**
     * Refresh an access token using a valid refresh token.
     *
     * @param  string  $refreshToken  The opaque refresh token
     * @param  string  $context  Either 'admin' or 'consumer'
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string}
     *
     * @throws AuthenticationException
     */
    public function refresh(string $refreshToken, string $context): array
    {
        $hash = hash('sha256', $refreshToken);

        /** @var RefreshToken|null $record */
        $record = RefreshToken::query()
            ->where('token_hash', $hash)
            ->where('context', $context)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            throw new AuthenticationException('Invalid refresh token.');
        }

        // Use auth() to find the user via the guard's provider
        $guard = $this->resolveGuardName($context);
        $provider = auth($guard)->getProvider();
        $user = $provider->retrieveById($record->tokenable_id);

        if (! $user) {
            $record->update(['revoked_at' => now()]);
            throw new AuthenticationException('User not found for refresh token.');
        }

        // Revoke old refresh token
        $record->update(['revoked_at' => now()]);

        // Issue new token pair
        $payload = $this->issue($user, $context);
        Event::dispatch(new TokenRefreshedEvent($context, $user, $payload));

        return $payload;
    }

    /**
     * Revoke a refresh token (logout).
     *
     * @param  string  $refreshToken  The opaque refresh token
     * @param  string  $context  Either 'admin' or 'consumer'
     */
    public function revoke(string $refreshToken, string $context): void
    {
        $hash = hash('sha256', $refreshToken);

        $revoked = RefreshToken::query()
            ->where('token_hash', $hash)
            ->where('context', $context)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        Event::dispatch(new TokenRevokedEvent($context, (int) $revoked));
    }

    /**
     * Resolve the guard name from context.
     *
     * @param  string  $context  Either 'admin' or 'consumer'
     */
    protected function resolveGuardName(string $context): string
    {
        return $this->context->guardForContext($context);
    }
}
