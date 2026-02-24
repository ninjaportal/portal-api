<?php

namespace NinjaPortal\Api\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NinjaPortal\Api\Services\RefreshTokenPruner;

class PruneRefreshTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RefreshTokenPruner $pruner): void
    {
        $deleted = $pruner->prune();

        logger()->info('Portal API refresh tokens pruned.', [
            'deleted' => $deleted,
        ]);
    }
}
