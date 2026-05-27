<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBatchedUserSyncs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            $cache = $this->cache();
            $userIds = $cache->pull('pending_user_syncs', []);

            if (empty($userIds)) {
                Log::debug('process_batched_user_syncs: nothing to process');
                return;
            }

            $cache->forget('pending_user_sync_scheduled');

            $unique = array_values(array_unique(array_map('intval', $userIds)));

            Log::info('process_batched_user_syncs', [
                'count' => count($unique),
                'user_ids' => $unique,
            ]);

            foreach ($unique as $userId) {
                // Dispatch existing job per-user but now deduplicated within a window
                \App\Jobs\SyncApplicationUsers::dispatch([], [], [], $userId);
            }
        } catch (\Throwable $e) {
            Log::error('process_batched_user_syncs_failed', ['error' => $e->getMessage()]);
        }
    }

    protected function cache()
    {
        return \Illuminate\Support\Facades\Cache::store((string) config('iam.sync_batch.cache_store', config('cache.default')));
    }
}
