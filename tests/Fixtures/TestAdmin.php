<?php

namespace NinjaPortal\Api\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestAdmin extends Authenticatable
{
    protected $table = 'portal_api_test_admins';
}
