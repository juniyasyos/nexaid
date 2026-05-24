<?php

namespace App\Observers;

use App\Jobs\SyncApplicationUsers;
use App\Models\UserAccessProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UserAccessProfileObserver
{
    /**
     * Handle the UserAccessProfile "created" event.
     * OPTIMIZATION: Batch job dispatch to prevent memory buildup
     */
    public function created(UserAccessProfile $userAccessProfile): void
    {
        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::info('iam.user_access_profile_created', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'assigned_by' => $userAccessProfile->assigned_by,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSyncBatched($user, 'access_profile_assigned');
            // OPTIMIZATION: Clear relationship cache
            $user->clearRelationshipCaches();
        }
    }

    /**
     * Handle the UserAccessProfile "updated" event.
     * OPTIMIZATION: Batch job dispatch to prevent memory buildup
     */
    public function updated(UserAccessProfile $userAccessProfile): void
    {
        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::info('iam.user_access_profile_updated', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'changed' => $userAccessProfile->getChanges(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSyncBatched($user, 'access_profile_updated');
            // OPTIMIZATION: Clear relationship cache
            $user->clearRelationshipCaches();
        }
    }

    /**
     * Handle the UserAccessProfile "deleted" event.
     * OPTIMIZATION: Batch job dispatch to prevent memory buildup
     */
    public function deleted(UserAccessProfile $userAccessProfile): void
    {
        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::warning('iam.user_access_profile_deleted', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSyncBatched($user, 'access_profile_removed');
            // OPTIMIZATION: Clear relationship cache
            $user->clearRelationshipCaches();
        }
    }

    /**
     * Handle the UserAccessProfile "restored" event.
     * OPTIMIZATION: Batch job dispatch to prevent memory buildup
     */
    public function restored(UserAccessProfile $userAccessProfile): void
    {
        $user = $userAccessProfile->user;
        $profile = $userAccessProfile->accessProfile;

        Log::info('iam.user_access_profile_restored', [
            'user_id' => $user?->id,
            'access_profile_id' => $profile?->id,
            'access_profile_name' => $profile?->name,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if ($user) {
            $this->dispatchSyncBatched($user, 'access_profile_restored');
            // OPTIMIZATION: Clear relationship cache
            $user->clearRelationshipCaches();
        }
    }

    /**
     * Dispatch sync job with batching to prevent memory buildup.
     * OPTIMIZATION: Uses cache to batch multiple sync requests
     */
    protected function dispatchSyncBatched($user, string $event): void
    {
        Log::info('iam.user_access_profile_trigger_sync', [
            'user_id' => $user->id,
            'event' => $event,
            'email' => $user->email,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Cache key to batch sync requests
        $cacheKey = "pending_sync_user.{$user->id}";

        // Only dispatch job if one hasn't been scheduled in the last 5 seconds
        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, 5);
            SyncApplicationUsers::dispatch([], [], [], $user->id);
        } else {
            Log::debug('iam.user_access_profile_sync_batched', [
                'user_id' => $user->id,
                'event' => $event,
                'reason' => 'Job already scheduled recently',
            ]);
        }
    }
}
