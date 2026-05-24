<?php

namespace App\Domain\Applications\Services;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Services\ApplicationRoleSyncService;
use App\Jobs\SyncApplicationUsers;

/**
 * Orchestrates application synchronization operations.
 * 
 * Consolidates business logic for:
 * - Role synchronization
 * - User synchronization
 * - Job dispatching
 * - Result formatting
 * 
 * Separates application layer from Filament UI layer, making logic
 * reusable in API endpoints, console commands, and event listeners.
 */
class ApplicationSyncOrchestrator
{
    /**
     * Sync roles for an application with IAM service.
     * 
     * Returns detailed result with:
     * - success: boolean
     * - message: string
     * - comparison: array with in_sync, missing_in_client, extra_in_client
     * - error: string (if failed)
     * 
     * @param Application $application
     * @return array Sync result with detailed information
     */
    public function syncRoles(Application $application): array
    {
        $service = new ApplicationRoleSyncService();
        $result = $service->syncRoles($application);

        dd([
            "result" => $result,
        ]);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'],
                'message' => 'Sincronisasi gagal',
            ];
        }

        // Enrich result with formatted comparison
        $comparison = $result['comparison'];
        $inSync = count($comparison['in_sync']);
        $missing = count($comparison['missing_in_client']);
        $extra = count($comparison['extra_in_client']);

        $message = "Sinkronisasi berhasil\n\n";
        $message .= "Status Saat Ini:\n";
        $message .= "✓ Tersinkronisasi: {$inSync} role\n";
        if ($missing > 0) {
            $message .= "⚠ Tidak ada di Klien: {$missing} role\n";
        }
        if ($extra > 0) {
            $message .= "ℹ Tambahan di Klien: {$extra} role";
        }

        return [
            'success' => true,
            'message' => $message,
            'comparison' => $comparison,
            'stats' => [
                'in_sync' => $inSync,
                'missing' => $missing,
                'extra' => $extra,
            ],
        ];
    }

    /**
     * Sync users for an application.
     * 
     * Gathers all access profiles that reference roles from this application
     * and dispatches a job to sync users. Maintains compatibility with previous
     * profile-based mechanism.
     * 
     * @param Application $application
     * @return void Job is dispatched asynchronously
     */
    public function syncUsers(Application $application): void
    {
        $profileIds = AccessProfile::query()
            ->whereHas('roles', function ($q) use ($application) {
                $q->where('application_id', $application->id);
            })
            ->pluck('id')
            ->toArray();

        SyncApplicationUsers::dispatch($application, $profileIds);
    }

    /**
     * Get sync statistics for an application.
     * 
     * Returns counts of:
     * - roles: number of roles for this application
     * - access_profiles: number of profiles using these roles
     * - users: approximate number of users affected
     * 
     * @param Application $application
     * @return array Sync statistics
     */
    public function getSyncStats(Application $application): array
    {
        $rolesCount = $application->roles()->count();

        $profilesCount = AccessProfile::query()
            ->whereHas('roles', function ($q) use ($application) {
                $q->where('application_id', $application->id);
            })
            ->count();

        return [
            'roles' => $rolesCount,
            'access_profiles' => $profilesCount,
            'affected_profiles' => $profilesCount,
        ];
    }

    /**
     * Format sync result for UI display.
     * 
     * Converts array result into a user-friendly notification message.
     * 
     * @param array $result Sync result from syncRoles()
     * @return string Formatted message
     */
    public function formatSyncResult(array $result): string
    {
        if (!$result['success']) {
            return sprintf("❌ %s\n%s", $result['message'], $result['error']);
        }

        return $result['message'];
    }
}
