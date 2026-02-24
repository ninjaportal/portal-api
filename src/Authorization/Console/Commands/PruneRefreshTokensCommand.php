<?php

namespace NinjaPortal\Api\Authorization\Console\Commands;

use Illuminate\Console\Command;
use NinjaPortal\Api\Jobs\PruneRefreshTokensJob;
use NinjaPortal\Api\Services\RefreshTokenPruner;

class   PruneRefreshTokensCommand extends Command
{
    protected $signature = 'portal-api:tokens:prune {--queue : Dispatch a queued prune job instead of pruning inline}';

    protected $description = 'Prune expired and revoked Portal API refresh tokens';

    public function handle(RefreshTokenPruner $pruner): int
    {
        if ((bool) $this->option('queue')) {
            PruneRefreshTokensJob::dispatch();
            $this->info('Queued refresh token prune job.');

            return self::SUCCESS;
        }

        $deleted = $pruner->prune();
        $this->info(sprintf('Pruned %d refresh token record(s).', $deleted));

        return self::SUCCESS;
    }
}
