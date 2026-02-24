<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Resources\ActivityLogResource;
use NinjaPortal\Api\Models\ActivityLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Admin: Activities
 */
class ActivitiesController extends Controller
{
    /**
     * List activity logs
     *
     * Requires permission: `portal.activities.view`.
     *
     * @authenticated
     *
     * @queryParam search string Example: portal-api.admin.users.update
     * @queryParam action string Example: portal-api.admin.users.update
     * @queryParam actor_type string Example: admin
     * @queryParam actor_id integer Example: 1
     * @queryParam subject_type string Example: User
     * @queryParam subject_id string Example: 10
     * @queryParam from string Example: 2026-01-01
     * @queryParam to string Example: 2026-01-31
     * @paginateRequest
     *
     * @paginatedResponse
     * @apiResourceCollection NinjaPortal\Api\Http\Resources\ActivityLogResource paginate=15
     * @apiResourceModel NinjaPortal\Api\Models\ActivityLog
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query();
        $this->applyActivityFilters($query, $request, includeSearch: true);

        [$perPage, $orderBy, $direction] = $this->listParams(
            $request,
            ['id', 'created_at', 'action', 'actor_email', 'subject_type'],
            'created_at'
        );

        $paginator = $query->orderBy($orderBy, strtolower($direction))->paginate($perPage);

        return $this->respondPaginated($request, $paginator, ActivityLogResource::class);
    }

    /**
     * Get activity log
     *
     * Requires permission: `portal.activities.view`.
     *
     * @authenticated
     * @apiResource NinjaPortal\Api\Http\Resources\ActivityLogResource
     * @apiResourceModel NinjaPortal\Api\Models\ActivityLog
     */
    public function show(Request $request, ActivityLog $activity)
    {
        return $this->respondResource($request, new ActivityLogResource($activity));
    }

    /**
     * Export activity logs as CSV
     *
     * Requires permission: `portal.activities.view`.
     *
     * @authenticated
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'activities-'.now()->format('Ymd-His').'.csv';

        $query = ActivityLog::query()->orderByDesc('id');
        $this->applyActivityFilters($query, $request, includeSearch: true);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'created_at',
                'action',
                'actor_type',
                'actor_id',
                'actor_email',
                'actor_name',
                'subject_type',
                'subject_id',
                'subject_label',
                'ip',
            ]);

            $query->chunk(1000, function ($chunk) use ($out) {
                foreach ($chunk as $row) {
                    fputcsv($out, [
                        $row->id,
                        $row->created_at?->toISOString(),
                        $row->action,
                        $row->actor_type,
                        $row->actor_id,
                        $row->actor_email,
                        $row->actor_name,
                        $row->subject_type,
                        $row->subject_id,
                        $row->subject_label,
                        $row->ip,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function applyActivityFilters($query, Request $request, bool $includeSearch = true): void
    {
        if ($includeSearch) {
            $search = trim((string) $request->query('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', '%'.$search.'%')
                        ->orWhere('actor_email', 'like', '%'.$search.'%')
                        ->orWhere('actor_name', 'like', '%'.$search.'%')
                        ->orWhere('subject_label', 'like', '%'.$search.'%')
                        ->orWhere('subject_type', 'like', '%'.$search.'%')
                        ->orWhere('subject_id', 'like', '%'.$search.'%');
                });
            }
        }

        foreach ([
            'action' => 'action',
            'actor_type' => 'actor_type',
            'actor_id' => 'actor_id',
            'subject_type' => 'subject_type',
            'subject_id' => 'subject_id',
        ] as $requestKey => $column) {
            $value = $request->query($requestKey);
            if ($value !== null && $value !== '') {
                $query->where($column, (string) $value);
            }
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', Carbon::parse((string) $from)->startOfDay());
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', Carbon::parse((string) $to)->endOfDay());
        }
    }
}
