<?php

namespace NinjaPortal\Api\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'portal_api_activity_logs';

    protected $fillable = [
        'action',
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_email',
        'subject_type',
        'subject_id',
        'subject_label',
        'ip',
        'user_agent',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];
}

