<?php

namespace NinjaPortal\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource;

        return [
            'id' => $log->getKey(),
            'action' => $log->action,
            'actor' => [
                'type' => $log->actor_type,
                'id' => $log->actor_id,
                'name' => $log->actor_name,
                'email' => $log->actor_email,
            ],
            'subject' => [
                'type' => $log->subject_type,
                'id' => $log->subject_id,
                'label' => $log->subject_label,
            ],
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
            'properties' => $log->properties,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }
}

