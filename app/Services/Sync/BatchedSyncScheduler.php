<?php

namespace App\Services\Sync;

use App\Jobs\SyncApplicationUsers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessBatchedUserSyncs;

class BatchedSyncScheduler
{
    protected const PENDING_KEY = 'pending_user_syncs';
    protected const SCHEDULE_KEY = 'pending_user_sync_scheduled';

    /**
     * Schedule a user id to be synchronized in a short batch window.
     * Multiple calls within the window will be aggregated.
     */
    public static function scheduleUser(int $userId, ?int $delaySeconds = null): void
    {
        if (! config('iam.sync_batch.enabled', true)) {
            SyncApplicationUsers::dispatch([], [], [], $userId);

            Log::info('batched_user_sync_fallback_dispatched', [
                'user_id' => $userId,
                'reason' => 'batching_disabled',
            ]);

            return;
        }

        $delaySeconds ??= (int) config('iam.sync_batch.delay_seconds', 5);

        $cache = self::cache();
        $pendingIds = $cache->get(self::PENDING_KEY, []);

        if (! is_array($pendingIds)) {
            $pendingIds = [];
        }

        $pendingIds[] = $userId;
        $pendingIds = array_values(array_unique(array_map('intval', $pendingIds)));
        $cache->put(self::PENDING_KEY, $pendingIds, now()->addHours(2));
        $pendingCount = count($pendingIds);

        // Ensure a single delayed job is scheduled for the batch window
        $added = $cache->add(self::SCHEDULE_KEY, true, $delaySeconds);

        Log::info('batched_user_sync_scheduled', [
            'user_id' => $userId,
            'delay_seconds' => $delaySeconds,
            'pending_count' => $pendingCount,
            'job_scheduled' => $added,
            'cache_store' => config('iam.sync_batch.cache_store', config('cache.default')),
        ]);

        if ($added) {
            ProcessBatchedUserSyncs::dispatch()->delay(now()->addSeconds($delaySeconds));
        }
    }

    protected static function cache()
    {
        return Cache::store((string) config('iam.sync_batch.cache_store', config('cache.default')));
    }
}
