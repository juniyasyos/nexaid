<?php

namespace App\Domain\Users\Services;

use App\Models\User;

/**
 * Formats user access summary information for display.
 * 
 * Consolidates IAM summary logic that was embedded in UsersTable.
 * Provides user-friendly formatting of access profiles and applications count.
 */
class UserAccessSummaryFormatter
{
    /**
     * Get formatted IAM summary string.
     * 
     * Example: "5 aplikasi • 3 profil akses"
     * 
     * @param User $user
     * @return string|null Formatted summary or null if no access profiles
     */
    public function format(User $user): ?string
    {
        $profilesCount = $this->getAccessProfilesCount($user);

        if ($profilesCount === 0) {
            return null;
        }

        $appsCount = $this->getAccessibleAppsCount($user);

        return sprintf('%d aplikasi • %d profil akses', $appsCount, $profilesCount);
    }

    /**
     * Get the count of access profiles for a user.
     * 
     * Uses eager loaded relationship if available, otherwise queries.
     * 
     * @param User $user
     * @return int
     */
    public function getAccessProfilesCount(User $user): int
    {
        return $user->relationLoaded('accessProfiles')
            ? $user->accessProfiles->count()
            : $user->accessProfiles()->count();
    }

    /**
     * Get the count of accessible applications for a user.
     * 
     * Uses the cached accessibleApps() method on the User model.
     * 
     * @param User $user
     * @return int
     */
    public function getAccessibleAppsCount(User $user): int
    {
        return count($user->accessibleApps());
    }

    /**
     * Get detailed breakdown of access information.
     * 
     * @param User $user
     * @return array{apps_count: int, profiles_count: int, apps: array}
     */
    public function getDetails(User $user): array
    {
        return [
            'apps_count' => $this->getAccessibleAppsCount($user),
            'profiles_count' => $this->getAccessProfilesCount($user),
            'apps' => $user->accessibleApps(),
        ];
    }
}
