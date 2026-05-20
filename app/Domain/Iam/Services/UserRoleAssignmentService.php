<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\Role;
use App\Domain\Iam\Models\UserApplicationRole;
use App\Models\User;
use Illuminate\Support\Collection;

class UserRoleAssignmentService
{
    /**
     * When non-empty, only these access profile IDs are considered when
     * syncing.  Used by the bulk sync job.
     *
     * @var array<int>
     */
    protected array $allowedProfileIds = [];

    /**
     * Configure which profiles may be used during role/profile sync. If left
     * empty all bundles are permitted.
     */
    public function setAllowedProfileIds(array $ids): void
    {
        $this->allowedProfileIds = $ids;
    }

    /**
     * Plan which access profiles would be used for given role slugs in an app.
     * This is a dry-run helper and does not modify the database.
     *
     * Returns array with existing profile candidates, covered slugs, missing
     * slugs, and expected auto-profile names when no existing profile covers a slug.
     */
    public function planProfilesForRoleSlugs(Application $app, array $roleSlugs): array
    {
        $roleSlugs = array_values(array_unique($roleSlugs));

        $roles = \App\Domain\Iam\Models\ApplicationRole::where('application_id', $app->id)
            ->whereIn('slug', $roleSlugs)
            ->get();

        $invalidSlugs = array_diff($roleSlugs, $roles->pluck('slug')->toArray());

        $existingProfiles = \App\Domain\Iam\Models\AccessProfile::query()
            ->whereHas('roles', function ($q) use ($app, $roleSlugs) {
                $q->where('application_id', $app->id)
                    ->whereIn('slug', $roleSlugs);
            })
            ->with('roles')
            ->get();

        $profileIds = $existingProfiles->pluck('id')->toArray();
        if (! empty($this->allowedProfileIds)) {
            $profileIds = array_intersect($profileIds, $this->allowedProfileIds);
            $existingProfiles = $existingProfiles->whereIn('id', $profileIds);
        }

        $coveredSlugs = $existingProfiles
            ->flatMap(fn($p) => $p->roles->pluck('slug'))
            ->unique()
            ->toArray();

        $missingSlugs = array_diff($roleSlugs, $coveredSlugs);

        $candidateProfiles = $existingProfiles->map(function ($profile) {
            return [
                'id' => $profile->id,
                'slug' => $profile->slug,
                'name' => $profile->name,
                'role_slugs' => $profile->roles->pluck('slug')->toArray(),
            ];
        })->toArray();

        $autoProfiles = array_map(function ($slug) use ($app) {
            return [
                'slug' => 'auto_' . $app->app_key . '_' . $slug,
                'name' => 'Auto ' . $slug,
                'role_slugs' => [$slug],
            ];
        }, $missingSlugs);

        return [
            'requested_role_slugs' => $roleSlugs,
            'invalid_role_slugs' => array_values($invalidSlugs),
            'covered_role_slugs' => array_values($coveredSlugs),
            'missing_role_slugs' => array_values($missingSlugs),
            'candidate_profiles' => $candidateProfiles,
            'auto_profiles' => $autoProfiles,
        ];
    }

    /**
     * Assign a role to a user.
     *
     * @throws \Exception
     */
    public function assignRoleToUser(User $user, UserApplicationRole $role, ?User $assignedBy = null): void
    {
        // Check if user already has this role
        $existing = UserApplicationRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->first();

        if ($existing) {
            throw new \Exception("User already has role '{$role->name}' for application '{$role->application->app_key}'.");
        }

        $data = [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'assigned_by' => $assignedBy?->id,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('iam_user_application_roles', 'application_id')) {
            $data['application_id'] = $role->application_id;
        }
        UserApplicationRole::create($data);
    }

    /**
     * Revoke a role from a user.
     */
    public function revokeRoleFromUser(User $user, UserApplicationRole $role): void
    {
        UserApplicationRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->delete();
    }

    /**
     * Sync roles for a user in a specific application.
     * This will replace all existing roles for the app with the provided role slugs.
     *
     * @param  array<string>  $roleSlugs
     *
     * @throws \Exception
     */
    /**
     * Sync roles for a user in a specific application by assigning *access
     * profiles* (aka role bundles) instead of attaching the role records
     * directly.  The client gives us a list of role slugs, but the database
     * model only links users -> access_profiles, and profiles themselves
     * contain the application roles.  This helper ensures the user is paired
     * with every profile that contains one of the requested slugs, and removes
     * profiles that no longer match the application.
     *
     * This method replaces the old direct-assignment behaviour and will throw
     * an exception if any slug is invalid.
     *
     * @param  array<string>  $roleSlugs
     *
     * @throws \Exception
     */
    public function syncProfilesForUserAndApp(User $user, Application $app, array $roleSlugs, ?User $assignedBy = null): void
    {
        // Skip disabled applications
        if (!$app->enabled) {
            \Illuminate\Support\Facades\Log::warning('user_sync_app_disabled', [
                'application_id' => $app->id,
                'app_key' => $app->app_key,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_nip' => $user->nip,
                'message' => 'Skipping role sync for disabled application',
            ]);

            return;
        }

        // always validate role slugs against the application roles table.
        // this avoids touching the old pivot entirely and works whether or not
        // the migration has been executed.
        $roles = \App\Domain\Iam\Models\ApplicationRole::where('application_id', $app->id)
            ->whereIn('slug', $roleSlugs)
            ->get();

        $foundRoleSlugs = $roles->pluck('slug')->toArray();
        $missingRoleSlugs = array_diff($roleSlugs, $foundRoleSlugs);

        // Log slug validation
        \Illuminate\Support\Facades\Log::info('user_sync_slug_validation', [
            'application_id' => $app->id,
            'app_key' => $app->app_key,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_nip' => $user->nip,
            'requested_slugs' => $roleSlugs,
            'found_slugs' => $foundRoleSlugs,
            'missing_slugs' => array_values($missingRoleSlugs),
            'total_requested' => count($roleSlugs),
            'total_found' => count($foundRoleSlugs),
            'validation_passed' => empty($missingRoleSlugs),
        ]);

        if ($roles->count() !== count($roleSlugs)) {
            \Illuminate\Support\Facades\Log::error('user_sync_slug_validation_failed', [
                'application_id' => $app->id,
                'app_key' => $app->app_key,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_nip' => $user->nip,
                'missing_slugs' => array_values($missingRoleSlugs),
            ]);
            throw new \Exception('Invalid role slugs: ' . implode(', ', $missingRoleSlugs));
        }

        // find all profiles that either contain one of the requested roles, or
        // whose own slug matches a requested role slug.
        $existingProfiles = \App\Domain\Iam\Models\AccessProfile::query()
            ->where(function ($q) use ($app, $roleSlugs) {
                $q->whereHas('roles', function ($q2) use ($app, $roleSlugs) {
                    $q2->where('application_id', $app->id)
                        ->whereIn('slug', $roleSlugs);
                })
                    ->orWhereIn('slug', $roleSlugs);
            })
            ->with('roles')
            ->get();

        $profileIds = $existingProfiles->pluck('id')->toArray();

        if (! empty($this->allowedProfileIds)) {
            $profileIds = array_intersect($profileIds, $this->allowedProfileIds);
        }

        $coveredSlugs = $existingProfiles
            ->flatMap(fn($p) => $p->roles->pluck('slug'))
            ->unique()
            ->toArray();

        $missingSlugs = array_diff($roleSlugs, $coveredSlugs);

        // Do not auto-create profiles for missing role slugs. Keep existing behavior
        // to avoid side effects in the client's profile management flow.
        if (! empty($missingSlugs) && empty($this->allowedProfileIds)) {
            // for visibility we might log or ignore; we keep skip-only behavior
            // so that clients can fix access profile definitions explicitly.
            //
            // e.g. Log::warning('Some requested roles were not covered by existing profiles', [...]);
        }

        // find all profiles that reference at least one of the supplied roles
        $existingProfiles = \App\Domain\Iam\Models\AccessProfile::query()
            ->whereHas('roles', function ($q) use ($app, $roleSlugs) {
                $q->where('application_id', $app->id)
                    ->whereIn('slug', $roleSlugs);
            })
            ->with('roles')
            ->get();

        $profileIds = $existingProfiles->pluck('id')->toArray();

        // if the caller restricted to a subset of profiles, apply that filter
        if (! empty($this->allowedProfileIds)) {
            $profileIds = array_intersect($profileIds, $this->allowedProfileIds);
        }

        // compute which slugs are already covered by the profiles we found
        $coveredRoleSlugs = $existingProfiles
            ->flatMap(fn($p) => $p->roles->pluck('slug'))
            ->toArray();

        $coveredProfileSlugs = $existingProfiles
            ->pluck('slug')
            ->toArray();

        $coveredSlugs = array_values(array_unique(array_merge($coveredRoleSlugs, $coveredProfileSlugs)));

        // for any slug that isn't covered yet, do not create new profiles.
        // Only update existing profiles if possible.
        $missingSlugs = array_diff($roleSlugs, $coveredSlugs);

        // Log access profile matching
        $profileDetails = $existingProfiles->map(function ($p) {
            return [
                'profile_id' => $p->id,
                'profile_slug' => $p->slug,
                'profile_name' => $p->name,
                'role_slugs' => $p->roles->pluck('slug')->toArray(),
            ];
        })->values()->toArray();

        \Illuminate\Support\Facades\Log::info('user_sync_profile_matching', [
            'application_id' => $app->id,
            'app_key' => $app->app_key,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_nip' => $user->nip,
            'requested_role_slugs' => $roleSlugs,
            'found_profiles' => $profileDetails,
            'covered_role_slugs' => $coveredSlugs,
            'missing_role_slugs' => array_values($missingSlugs),
            'profile_count_found' => count($profileIds),
            'allowed_profile_ids' => $this->allowedProfileIds ?: 'none',
        ]);

        if (! empty($missingSlugs)) {
            \Illuminate\Support\Facades\Log::warning('user_sync_missing_role_coverage', [
                'application_id' => $app->id,
                'app_key' => $app->app_key,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_nip' => $user->nip,
                'missing_slugs' => array_values($missingSlugs),
                'note' => 'These role slugs are not covered by any access profile. Create access profiles or link them to existing profiles.',
            ]);
        }

        if (! empty($missingSlugs) && empty($this->allowedProfileIds)) {
            // OPTIMIZATION: Fetch all missing roles in one query instead of per-slug loop
            $missingRoles = \App\Domain\Iam\Models\ApplicationRole::where('application_id', $app->id)
                ->whereIn('slug', $missingSlugs)
                ->get()
                ->keyBy('slug');

            foreach ($missingSlugs as $slug) {
                $role = $missingRoles->get($slug);

                if (! $role) {
                    continue;
                }

                // Ensure each role has a corresponding profile with same slug
                $profile = $this->ensureProfileForRole($role);
                $profileIds[] = $profile->id;

                \Illuminate\Support\Facades\Log::info('user_sync_auto_created_profile', [
                    'application_id' => $app->id,
                    'app_key' => $app->app_key,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_nip' => $user->nip,
                    'role_slug' => $slug,
                    'profile_id' => $profile->id,
                    'profile_slug' => $profile->slug,
                    'profile_name' => $profile->name,
                ]);
            }
        }

        // current profiles of user that relate to this app; we will only add
        // new bundles and never remove existing ones, because removals should
        // be explicit. previously the code detached anything that wasn't part
        // of the incoming list, which caused associations to vanish during
        // sync if the client payload didn't include the corresponding role.
        $currentProfileIds = $user->accessProfiles()
            ->whereHas('roles', function ($q) use ($app) {
                $q->where('application_id', $app->id);
            })
            ->pluck('access_profiles.id')
            ->toArray();

        // attach only profiles that are not already present
        $toAdd = array_diff($profileIds, $currentProfileIds);

        \Illuminate\Support\Facades\Log::info('user_sync_profile_attachment', [
            'application_id' => $app->id,
            'app_key' => $app->app_key,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_nip' => $user->nip,
            'all_profiles_to_attach' => $profileIds,
            'current_profile_ids' => $currentProfileIds,
            'new_profiles_to_attach' => array_values($toAdd),
            'attachment_count' => count($toAdd),
        ]);

        if (! empty($toAdd)) {
            $user->accessProfiles()->attach($toAdd, ['assigned_by' => $assignedBy?->id]);

            \Illuminate\Support\Facades\Log::info('user_sync_profile_attached_success', [
                'application_id' => $app->id,
                'app_key' => $app->app_key,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_nip' => $user->nip,
                'attached_profile_ids' => array_values($toAdd),
                'attached_count' => count($toAdd),
            ]);
        } else {
            \Illuminate\Support\Facades\Log::info('user_sync_no_new_profiles', [
                'application_id' => $app->id,
                'app_key' => $app->app_key,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_nip' => $user->nip,
                'message' => 'User already has all required profiles',
            ]);
        }
    }

    /**
     * Resolve or create an access profile for a given application role.
     * The profile slug is set to the role slug to support the requested
     * behavior that role => profile slug mapping is 1:1.
     */
    protected function ensureProfileForRole(\App\Domain\Iam\Models\ApplicationRole $role): \App\Domain\Iam\Models\AccessProfile
    {
        $profile = \App\Domain\Iam\Models\AccessProfile::firstOrCreate(
            ['slug' => $role->slug],
            [
                'name' => $role->name ?: ucfirst($role->slug),
                'description' => 'Auto-created profile from role ' . $role->slug . ' for app ' . $role->application->app_key,
                'is_system' => false,
                'is_active' => true,
            ]
        );

        if (! $profile->roles()->where('iam_roles.id', $role->id)->exists()) {
            $profile->roles()->attach($role->id);
        }

        return $profile;
    }

    /**
     * @deprecated use {@see syncProfilesForUserAndApp} instead. kept for
     * backwards compatibility until callers are updated.
     */
    public function syncRolesForUserAndApp(User $user, Application $app, array $roleSlugs, ?User $assignedBy = null): void
    {
        $this->syncProfilesForUserAndApp($user, $app, $roleSlugs, $assignedBy);
    }

    /**
     * Get roles grouped by app_key for a user.
     * Returns: ['app_key' => ['slug1', 'slug2'], ...].
     *
     * @return array<string, array<string>>
     */
    public function getRolesByAppForUser(User $user): array
    {
        return $user->rolesByApp();
    }

    /**
     * Get list of app_keys that the user has access to.
     *
     * @return array<string>
     */
    public function getAppsForUser(User $user): array
    {
        return $user->accessibleApps();
    }

    /**
     * Get all roles assigned to a user for a specific application.
     */
    public function getRolesForUserInApp(User $user, Application $app): Collection
    {
        return UserApplicationRole::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('application_id', $app->id)
            ->get();
    }

    /**
     * Check if user has a specific role in an application.
     */
    public function userHasRole(User $user, string $appKey, string $roleSlug): bool
    {
        $rolesByApp = $this->getRolesByAppForUser($user);

        return isset($rolesByApp[$appKey]) && in_array($roleSlug, $rolesByApp[$appKey]);
    }

    /**
     * Ensure the user has access profiles matching any direct app roles they already have.
     * This supports upgrade path where roles may be assigned directly first.
     */
    public function syncProfilesFromExistingAppRoles(User $user, Application $app): void
    {
        $roleSlugs = $user->applicationRoles()
            ->where('iam_roles.application_id', $app->id)
            ->pluck('slug')
            ->toArray();

        if (empty($roleSlugs)) {
            return;
        }

        // Avoid infinite loop: call syncProfilesForUserAndApp with current role slugs.
        $this->syncProfilesForUserAndApp($user, $app, $roleSlugs);
    }

    /**
     * Get all users with a specific role.
     */
    public function getUsersWithRole(UserApplicationRole $role): Collection
    {
        return $role->users;
    }

    /**
     * Revoke all roles from a user for a specific application.
     */
    public function revokeAllRolesForUserInApp(User $user, Application $app): void
    {
        UserApplicationRole::where('user_id', $user->id)
            ->whereHas('role', function ($query) use ($app) {
                $query->where('application_id', $app->id);
            })
            ->delete();
    }
}
