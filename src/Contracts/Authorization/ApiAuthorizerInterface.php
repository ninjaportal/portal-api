<?php

namespace NinjaPortal\Api\Contracts\Authorization;

interface ApiAuthorizerInterface
{
    /**
     * @param  mixed  $arguments
     */
    public function authorize(string $ability, mixed $arguments = []): void;
}
