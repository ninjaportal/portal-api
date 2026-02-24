<?php

namespace NinjaPortal\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RefreshToken extends Model
{
    protected $table = 'portal_api_refresh_tokens';

    protected $fillable = [
        'context',
        'token_hash',
        'tokenable_type',
        'tokenable_id',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }
}
