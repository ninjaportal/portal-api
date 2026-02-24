<?php

namespace NinjaPortal\Api\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use NinjaPortal\Api\Contracts\Authorization\ApiAuthorizerInterface;

class PolicyApiAuthorizer implements ApiAuthorizerInterface
{
    public function __construct(protected Gate $gate) {}

    public function authorize(string $ability, mixed $arguments = []): void
    {
        $this->gate->authorize($ability, $arguments);
    }
}
