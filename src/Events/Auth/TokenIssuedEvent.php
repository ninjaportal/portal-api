<?php

namespace NinjaPortal\Api\Events\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class TokenIssuedEvent
{
    /**
     * @param  array{token_type: string, access_token: string, expires_in: int, refresh_token: string}  $payload
     */
    public function __construct(
        public string $context,
        public Authenticatable $user,
        public array $payload
    ) {}
}
