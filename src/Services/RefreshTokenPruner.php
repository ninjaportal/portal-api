<?php

namespace NinjaPortal\Api\Services;

use NinjaPortal\Api\Models\RefreshToken;

class RefreshTokenPruner
{
    public function prune(): int
    {
        return RefreshToken::query()
            ->where(function ($query) {
                $query->whereNotNull('revoked_at')
                    ->orWhere('expires_at', '<=', now());
            })
            ->delete();
    }
}
