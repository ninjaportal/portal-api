<?php

namespace NinjaPortal\Api\Authorization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NinjaPortal\Api\Jobs\RecordActivityJob;
use NinjaPortal\Api\Models\ActivityLog;
use NinjaPortal\Api\Support\PortalApiContext;
use NinjaPortal\Portal\Events\Admin\AdminCreatedEvent;

class ActivityHealthCheckCommand extends Command
{
    protected $signature = 'portal-api:activity:health-check
        {--queue : Also enqueue a test job using the current queue connection}
        {--cleanup : Delete generated health-check rows after the command finishes}';

    protected $description = 'Diagnose Portal API activity logging and verify that activity rows can be recorded.';

    public function handle(PortalApiContext $context): int
    {
        $this->components->info('Portal API activity logging health check');
        $this->newLine();

        $this->line(sprintf('Activity enabled: <info>%s</info>', config('portal-api.activity.enabled', true) ? 'yes' : 'no'));
        $this->line(sprintf('Activity queue mode: <info>%s</info>', config('portal-api.activity.queue', false) ? 'queued' : 'sync'));
        $this->line(sprintf('Queue connection: <info>%s</info>', (string) config('queue.default')));
        $this->line(sprintf('Admin guard: <info>%s</info>', $context->guardForContext('admin')));
        $this->line(sprintf('API provider loaded: <info>%s</info>', app()->providerIsLoaded(\NinjaPortal\Api\ApiServiceProvider::class) ? 'yes' : 'no'));

        $listenerCount = count(app('events')->getListeners(AdminCreatedEvent::class));
        $this->line(sprintf('Listeners for AdminCreatedEvent: <info>%d</info>', $listenerCount));

        if (! config('portal-api.activity.enabled', true)) {
            $this->components->error('Activity logging is disabled by config. Enable PORTAL_API_ACTIVITY_ENABLED first.');

            return self::FAILURE;
        }

        $action = 'portal.activity.health_check.'.now()->format('YmdHis');
        $beforeCount = ActivityLog::query()->count();
        RecordActivityJob::dispatchSync([
            'action' => $action,
            'actor_type' => 'admin',
            'actor_id' => 999999,
            'actor_name' => 'Activity Health Check',
            'actor_email' => 'activity.healthcheck@ninjaportal.test',
            'subject_type' => 'ActivityHealthCheck',
            'subject_id' => '999999',
            'subject_label' => 'activity.healthcheck@ninjaportal.test',
            'ip' => '127.0.0.1',
            'user_agent' => 'portal-api:activity:health-check',
            'properties' => [
                'route' => 'portal-api.activity.health-check',
                'method' => 'CLI',
                'uri' => 'portal-api:activity:health-check',
                'listener_count' => $listenerCount,
            ],
        ]);

        $activity = ActivityLog::query()
            ->where('action', $action)
            ->latest('id')
            ->first();

        if (! $activity) {
            $this->components->error('Sync write check failed: no activity row was written.');

            return self::FAILURE;
        }

        $afterCount = ActivityLog::query()->count();
        $this->components->info(sprintf(
            'Sync write check passed: activity row #%d recorded immediately (%d -> %d rows).',
            $activity->getKey(),
            $beforeCount,
            $afterCount,
        ));
        $this->line(sprintf('Recorded action: <info>%s</info>', (string) $activity->action));
        $this->line(sprintf('Recorded actor type: <info>%s</info>', (string) $activity->actor_type));
        $this->line(sprintf(
            'Listener registration status: <info>%s</info>',
            $listenerCount > 0 ? 'registered' : 'missing'
        ));

        if ((bool) $this->option('queue')) {
            $this->newLine();
            $this->components->info('Running queued dispatch diagnostic...');

            $beforeJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            RecordActivityJob::dispatch([
                'action' => $action.'.queued',
                'actor_type' => 'admin',
                'actor_id' => 999999,
                'actor_name' => 'Activity Health Check',
                'actor_email' => 'activity.healthcheck@ninjaportal.test',
                'subject_type' => 'Admin',
                'subject_id' => '999999',
                'subject_label' => 'activity.healthcheck@ninjaportal.test',
                'ip' => '127.0.0.1',
                'user_agent' => 'portal-api:activity:health-check',
                'properties' => [
                    'route' => 'portal-api.activity.health-check',
                    'method' => 'CLI',
                    'uri' => 'portal-api:activity:health-check',
                ],
            ]);

            $afterJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $this->line(sprintf('Queued jobs before: <info>%d</info>', $beforeJobs));
            $this->line(sprintf('Queued jobs after: <info>%d</info>', $afterJobs));

            if ($afterJobs > $beforeJobs) {
                $this->components->info('Queue diagnostic passed: a RecordActivityJob is waiting in the queue.');
            } else {
                $this->components->warn('Queue diagnostic did not increase the jobs table. Check your queue connection/driver.');
            }
        }

        if ((bool) $this->option('cleanup')) {
            ActivityLog::query()
                ->where('actor_email', 'activity.healthcheck@ninjaportal.test')
                ->delete();

            $this->newLine();
            $this->components->info('Cleaned up generated activity health-check rows.');
        }

        return self::SUCCESS;
    }
}
