<?php

namespace NinjaPortal\Api\Events\Auth;

class LoginAttemptedEvent
{
    public function __construct(
        public string $context,
        public string $email
    ) {}
}
