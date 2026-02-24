<?php

namespace NinjaPortal\Api\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestConsumer extends Authenticatable
{
    protected $table = 'portal_api_test_consumers';
}
