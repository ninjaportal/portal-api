<?php

namespace NinjaPortal\Api\Contracts\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthFlowInterface
{
    /**
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string}
     */
    public function attemptLogin(string $email, string $password, string $context): array;

    /**
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string}
     */
    public function issueForUser(Authenticatable $user, string $context): array;

    /**
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string}
     */
    public function refresh(string $refreshToken, string $context): array;

    public function logout(string $refreshToken, string $context): void;
}
