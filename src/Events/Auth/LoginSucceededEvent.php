<?php

namespace NinjaPortal\Api\Events\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class LoginSucceededEvent
{
    public function __construct(
        public string $context,
        public string $email,
        public Authenticatable $user
    ) {}
}
