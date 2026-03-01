<?php

namespace NinjaPortal\Api\Models;

use NinjaPortal\Api\Auth\Concerns\HasPortalApiJwtSubject;
use NinjaPortal\Portal\Models\Admin as PortalAdmin;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Admin extends PortalAdmin implements JWTSubject
{
    use HasPortalApiJwtSubject;

    protected string $portalApiJwtContext = 'admin';
}
