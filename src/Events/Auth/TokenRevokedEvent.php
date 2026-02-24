<?php

namespace NinjaPortal\Api\Events\Auth;

class TokenRevokedEvent
{
    public function __construct(
        public string $context,
        public int $revokedCount = 0
    ) {}
}
