<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ProcessBatchedUserSyncs;

class BatchedSyncScheduler
{
    protected const PENDING_SET = 'pending_user_syncs';
    protected const SCHEDULE_KEY = 'pending_user_sync_scheduled';

    /**
     * Schedule a user id to be synchronized in a short batch window.
     * Multiple calls within the window will be aggregated.
     */
    public static function scheduleUser(int $userId, int $delaySeconds = 5): void
    {
        // Add user id to Redis set (idempotent)
        Redis::sadd(self::PENDING_SET, (string) $userId);
        $pendingCount = (int) Redis::scard(self::PENDING_SET);

        // Ensure a single delayed job is scheduled for the batch window
        $added = Cache::add(self::SCHEDULE_KEY, true, $delaySeconds);

        Log::info('batched_user_sync_scheduled', [
            'user_id' => $userId,
            'delay_seconds' => $delaySeconds,
            'pending_count' => $pendingCount,
            'job_scheduled' => $added,
        ]);

        if ($added) {
            ProcessBatchedUserSyncs::dispatch()->delay(now()->addSeconds($delaySeconds));
        }
    }
}
