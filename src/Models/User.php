<?php

namespace NinjaPortal\Api\Models;

use NinjaPortal\Api\Auth\Concerns\HasPortalApiJwtSubject;
use NinjaPortal\Portal\Models\User as PortalUser;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends PortalUser implements JWTSubject
{
    use HasPortalApiJwtSubject;

    protected string $portalApiJwtContext = 'user';
}
