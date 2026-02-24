<?php

namespace NinjaPortal\Api\Events\Auth;

class LoginFailedEvent
{
    public function __construct(
        public string $context,
        public string $email,
        public string $reason = 'failed'
    ) {}
}
