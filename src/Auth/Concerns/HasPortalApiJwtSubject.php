<?php

namespace NinjaPortal\Api\Auth\Concerns;

trait HasPortalApiJwtSubject
{
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'ctx' => $this->portalApiJwtContext(),
        ];
    }

    protected function portalApiJwtContext(): string
    {
        return property_exists($this, 'portalApiJwtContext')
            ? (string) $this->portalApiJwtContext
            : 'consumer';
    }
}
