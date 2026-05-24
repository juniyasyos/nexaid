<?php

namespace App\Observers;

use App\Domain\Iam\Models\UserApplicationRole;
use App\Jobs\SyncApplicationUsers;
use Illuminate\Support\Facades\Log;

class UserApplicationRoleObserver
{
    public function created(UserApplicationRole $assignment): void
    {
        $this->dispatchForUser($assignment->user_id, 'application_role_created');
    }

    public function updated(UserApplicationRole $assignment): void
    {
        $this->dispatchForUser($assignment->user_id, 'application_role_updated');
    }

    public function deleted(UserApplicationRole $assignment): void
    {
        $this->dispatchForUser($assignment->user_id, 'application_role_deleted');
    }

    protected function dispatchForUser(?int $userId, string $event): void
    {
        if (! $userId) {
            return;
        }

        Log::info('iam.application_role_observer_trigger', [
            'user_id' => $userId,
            'event' => $event,
        ]);

        SyncApplicationUsers::dispatch([], [], [], $userId);
    }
}
