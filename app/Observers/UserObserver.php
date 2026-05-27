<?php

namespace App\Observers;

use App\Jobs\SyncApplicationUsers;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Attributes changes that should trigger client sync.
     *
     * @var array<string>
     */
    protected array $syncAttributes = [
        'nip',
        'name',
        'email',
        'active',
    ];

    public function __construct()
    {
        // Conditionally include password field based on config
        if (setting('iam.user_sync_password_field', false)) {
            $this->syncAttributes[] = 'password';
        }
    }

    public function saved(User $user): void
    {
        if (! $user->wasRecentlyCreated && ! $user->wasChanged($this->syncAttributes)) {
            return;
        }

        $originalData = [];
        foreach ($this->syncAttributes as $attr) {
            $originalData[$attr] = $user->getOriginal($attr);
        }

        Log::info('iam.user_observer_saved', [
            'user_id' => $user->id,
            'nip' => $user->nip,
            'name' => $user->name,
            'email' => $user->email,
            'active' => $user->active,
            'created' => $user->wasRecentlyCreated,
            'changed' => $user->wasChanged($this->syncAttributes) ? $user->getChanges() : [],
            'original' => $originalData,
            'timestamp' => now()->toDateTimeString(),
        ]);

        Log::info('iam.user_observer_saved_detail', [
            'event' => 'saved',
            'user_id' => $user->id,
            'was_recently_created' => $user->wasRecentlyCreated,
            'updated_attributes' => $user->wasChanged($this->syncAttributes) ? array_keys($user->getChanges()) : [],
        ]);

        // OPTIMIZATION: Clear relationship caches after user save
        $user->clearRelationshipCaches();

        $this->dispatchUserSync($user, 'saved');
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged($this->syncAttributes)) {
            return;
        }

        Log::info('iam.user_observer_updated', [
            'user_id' => $user->id,
            'nip' => $user->nip,
            'name' => $user->name,
            'email' => $user->email,
            'active' => $user->active,
            'changed_attributes' => $user->getChanges(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // OPTIMIZATION: Clear relationship caches after user update
        $user->clearRelationshipCaches();

        $this->dispatchUserSync($user, 'updated');
    }

    public function deleted(User $user): void
    {
        Log::warning('iam.user_observer_deleted', [
            'user_id' => $user->id,
            'nip' => $user->nip,
            'email' => $user->email,
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // OPTIMIZATION: Clear relationship caches after user delete
        $user->clearRelationshipCaches();

        $this->dispatchUserSync($user, 'deleted');
    }

    public function restored(User $user): void
    {
        Log::info('iam.user_observer_restored', [
            'user_id' => $user->id,
            'nip' => $user->nip,
            'email' => $user->email,
            'restored_at' => $user->updated_at?->toDateTimeString(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // OPTIMIZATION: Clear relationship caches after user restore
        $user->clearRelationshipCaches();

        $this->dispatchUserSync($user, 'restored');
    }

    public function forceDeleted(User $user): void
    {
        Log::warning('iam.user_observer_force_deleted', [
            'user_id' => $user->id,
            'nip' => $user->nip,
            'email' => $user->email,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Force delete all user_unit_kerja relations
        if (method_exists($user, 'unitKerjas')) {
            \Illuminate\Support\Facades\DB::table('user_unit_kerja')
                ->where('user_id', $user->id)
                ->delete();

            Log::info('iam.user_unit_kerja_force_deleted', [
                'user_id' => $user->id,
                'nip' => $user->nip,
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        $this->dispatchUserSync($user, 'force_deleted');
    }

    /**
     * Triggered from relationship events / role assignment operations.
     * OPTIMIZATION: Avoid loading all roles and permissions to prevent memory issues
     */
    public function relationshipChanged(User $user, string $note = 'related'): void
    {
        // OPTIMIZATION: Only log count instead of loading all roles/permissions
        $rolesCount = $user->relationLoaded('roles') ? $user->roles->count() : $user->roles()->count();
        $permissionsCount = $user->relationLoaded('permissions') ? count($user->getPermissionNames()) : 0;

        Log::info('iam.user_observer_relationship_changed', [
            'user_id' => $user->id,
            'note' => $note,
            'roles_count' => $rolesCount,
            'permissions_count' => $permissionsCount,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // OPTIMIZATION: Clear relationship caches
        $user->clearRelationshipCaches();

        $this->dispatchUserSync($user, "relationship:{$note}");
    }

    protected function dispatchUserSync(User $user, string $event): void
    {
        $changed = $user->wasChanged($this->syncAttributes) ? $user->getChanges() : [];

        Log::info('iam.user_observer_trigger', [
            'user_id' => $user->id,
            'event' => $event,
            'changed_attributes' => $changed,
            'current' => $user->only(array_unique(array_merge(['id'], $this->syncAttributes))),
        ]);

        // Schedule batched sync to avoid immediate fan-out on rapid changes
        \App\Services\Sync\BatchedSyncScheduler::scheduleUser($user->id);
        Log::debug('iam.user_observer_sync_scheduled', [
            'user_id' => $user->id,
            'event' => $event,
        ]);
    }
}
