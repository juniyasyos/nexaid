<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserUnitKerjaObserver
{
    /**
     * Handle the model "deleting" event.
     * Track deleted pivot relations before they're removed from database.
     */
    public function deleting(Model $model): void
    {
        // Check if this is a user_unit_kerja pivot table deletion
        if ($model->getTable() !== 'user_unit_kerja') {
            return;
        }

        $userId = $model->user_id ?? null;
        $unitKerjaId = $model->unit_kerja_id ?? null;

        if (!$userId || !$unitKerjaId) {
            return;
        }

        // Get user and unit data before they're deleted
        $user = \App\Models\User::find($userId);
        $unit = \App\Models\UnitKerja::withTrashed()->find($unitKerjaId);

        if (!$user || !$unit) {
            return;
        }

        // Build deleted relation record
        $detachedRelation = [
            'user_id' => $userId,
            'user_nip' => $user->nip,
            'user_email' => $user->email,
            'unit_kerja_id' => $unitKerjaId,
            'unit_slug' => $unit->slug,
            'detached_at' => now()->toIso8601String(),
        ];

        // Store in cache for push users to pick up
        $cacheKey = "deleted_user_unit_kerja_for_push";
        $allDeleted = Cache::get($cacheKey, []);
        $allDeleted[] = $detachedRelation;

        // Store for 24 hours
        Cache::put($cacheKey, $allDeleted, now()->addDay());

        Log::info('iam.user_unit_kerja_pivot_detached', [
            'user_id' => $userId,
            'user_nip' => $user->nip,
            'unit_kerja_id' => $unitKerjaId,
            'unit_slug' => $unit->slug,
            'cached_count' => count($allDeleted),
        ]);
    }
}
