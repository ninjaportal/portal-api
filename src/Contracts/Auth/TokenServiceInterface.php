<?php

namespace NinjaPortal\Api\Contracts\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

interface TokenServiceInterface
{
    /**
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string}
     */
    public function issue(Authenticatable $user, string $context): array;

    /**
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string}
     */
    public function refresh(string $refreshToken, string $context): array;

    public function revoke(string $refreshToken, string $context): void;
}
