<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Models\User;
use Illuminate\Support\Collection;

class UserDataService
{
    /**
     * Extract primary URL from redirect_uris (flexible format support).
     * Handles both string and array formats for backward compatibility.
     *
     * @param mixed $redirectUris String or array of redirect URIs
     * @return string|null Primary URL or null if empty
     */
    private function getPrimaryUrl(mixed $redirectUris): ?string
    {
        if (empty($redirectUris)) {
            return null;
        }

        // If it's an array, get first element
        if (is_array($redirectUris)) {
            return !empty($redirectUris) ? $redirectUris[0] : null;
        }

        // If it's a string, return it directly
        if (is_string($redirectUris)) {
            return trim($redirectUris);
        }

        return null;
    }

    /**
     * Get comprehensive user data for SSO/API responses.
     *
     * @param User $user
     * @param Application|null $application Filter by specific application
     * @param bool $includeProfiles Include access profile information
     * @return array
     */
    public function getUserData(User $user, ?Application $application = null, bool $includeProfiles = true): array
    {
        $data = $this->buildUserFields($user);

        // Get all effective roles (direct + via access profiles)
        $effectiveRoles = $user->effectiveApplicationRoles()->with('application')->get();

        if ($application) {
            // Filter roles for specific application
            $data['application'] = [
                'app_key' => $application->app_key,
                'name' => $application->name,
                'roles' => $this->formatRolesForApplication($effectiveRoles, $application),
            ];
        } else {
            // Include all applications and roles
            $data['applications'] = $this->formatAllApplicationsAndRoles($effectiveRoles);
            $data['accessible_apps'] = $effectiveRoles->pluck('application.app_key')->unique()->values()->toArray();
        }

        // Include access profiles if requested
        if ($includeProfiles) {
            $data['access_profiles'] = $this->formatAccessProfiles($user);
        }

        // Include direct role assignments
        $data['direct_roles'] = $this->formatDirectRoles($user, $application);

        return $data;
    }

    /**
     * Build user fields based on configuration.
     * 
     * @param User $user
     * @return array
     */
    private function buildUserFields(User $user): array
    {
        $fields = collect(explode(',', setting('iam.user_fields', 'id,name,nip,email,status')))
            ->map('trim')
            ->filter()
            ->toArray();

        $data = [];
        $fieldMappings = [
            'id' => fn() => $user->id,
            'nip' => fn() => $user->nip ?? null,
            'name' => fn() => $user->name,
            'email' => fn() => $user->email,
            'status' => fn() => $user->status,
            'active' => fn() => $user->status === 'active',
            'email_verified_at' => fn() => $user->email_verified_at?->toIso8601String(),
            'created_at' => fn() => $user->created_at?->toIso8601String(),
            'updated_at' => fn() => $user->updated_at?->toIso8601String(),
        ];

        foreach ($fields as $field) {
            if (isset($fieldMappings[$field])) {
                $data[$field] = $fieldMappings[$field]();
            } elseif ($user->hasAttribute($field)) {
                // Support for custom user attributes
                $data[$field] = $user->getAttribute($field);
            }
        }

        return $data;
    }

    /**
     * Format roles for a specific application.
     */
    private function formatRolesForApplication(Collection $roles, Application $application): array
    {
        return $roles
            ->where('application_id', $application->id)
            ->map(fn($role) => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'description' => $role->description,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Format all applications and their roles.
     * Skip disabled applications.
     */
    private function formatAllApplicationsAndRoles(Collection $roles): array
    {
        return $roles
            ->groupBy('application.app_key')
            ->map(function ($appRoles, $appKey) {
                $firstRole = $appRoles->first();
                $app = $firstRole->application;

                // Skip disabled applications
                if (!$app->enabled) {
                    return null;
                }

                $primaryUrl = $this->getPrimaryUrl($app->redirect_uris);

                return [
                    'id' => $app->id,
                    'app_key' => $appKey,
                    'name' => $app->name,
                    'description' => $app->description,
                    'enabled' => $app->enabled,
                    'logo_url' => $app->logo_url,
                    'app_url' => $primaryUrl,
                    'redirect_uris' => $app->redirect_uris ?? [],
                    'roles' => $appRoles->map(fn($role) => [
                        'id' => $role->id,
                        'slug' => $role->slug,
                        'name' => $role->name,
                        'is_system' => $role->is_system,
                        'description' => $role->description,
                    ])->values()->toArray(),
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Format access profiles.
     * Skip roles from disabled applications.
     */
    private function formatAccessProfiles(User $user): array
    {
        return $user->accessProfiles()
            ->with('roles.application')
            ->where('is_active', true)
            ->get()
            ->map(function ($profile) {
                // Filter out roles from disabled applications
                $enabledRoles = $profile->roles->filter(fn($role) => $role->application && $role->application->enabled);

                // Skip profile if no enabled roles
                if ($enabledRoles->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $profile->id,
                    'slug' => $profile->slug,
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'is_system' => $profile->is_system,
                    'roles_count' => $enabledRoles->count(),
                    'roles' => $enabledRoles->map(fn($role) => [
                        'app_key' => $role->application->app_key,
                        'role_slug' => $role->slug,
                        'role_name' => $role->name,
                    ])->toArray(),
                ];
            })
            ->filter()
            ->toArray();
    }

    /**
     * Format direct role assignments (not via profiles).
     * Skip disabled applications.
     */
    private function formatDirectRoles(User $user, ?Application $application = null): array
    {
        $query = $user->applicationRoles()->with('application');

        if ($application) {
            // avoid the `pivot` alias entirely by referencing the concrete
            // column name. the relationship automatically joins the
            // iam_user_application_roles table, so qualifying the condition
            // with that table prevents the builder from inventing an alias and
            // causing the "unknown column 'pivot'" error we kept seeing in
            // previous logs.
            $query->where('iam_user_application_roles.application_id', $application->id);
        }

        return $query->get()
            ->filter(fn($role) => $role->application && $role->application->enabled)
            ->map(fn($role) => [
                'app_key' => $role->application->app_key,
                'role_id' => $role->id,
                'role_slug' => $role->slug,
                'role_name' => $role->name,
                'is_system' => $role->is_system,
            ])
            ->toArray();
    }

    /**
     * Get user data for JWT token payload.
     */
    public function getTokenPayload(User $user, Application $application): array
    {
        $userData = $this->getUserData($user, $application, false);

        return [
            'sub' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => !is_null($user->email_verified_at),
            'app_key' => $application->app_key,
            'roles' => $userData['application']['roles'] ?? [],
            'iat' => time(),
            'exp' => time() + $application->getTokenExpirySeconds(),
        ];
    }

    /**
     * Get user's applications organized by access profiles.
     * Structure: access profile -> list of applications with roles
     */
    public function getUserApplicationsByAccessProfile(User $user): array
    {
        $profiles = $user->accessProfiles()
            ->where('is_active', true)
            ->with(['roles' => function ($query) {
                $query->where('iam_roles.application_id', '!=', null);
            }, 'roles.application' => function ($query) {
                $query->where('enabled', true);
            }])
            ->get();

        $result = [];

        foreach ($profiles as $profile) {
            $applications = [];

            foreach ($profile->roles as $role) {
                $app = $role->application;

                if (!$app) {
                    continue;
                }

                $primaryUrl = $this->getPrimaryUrl($app->redirect_uris);

                $applications[] = [
                    'id' => $app->id,
                    'app_key' => $app->app_key,
                    'name' => $app->name,
                    'description' => $app->description,
                    'enabled' => $app->enabled,
                    'logo_url' => $app->logo_url,
                    'app_url' => $primaryUrl,
                    'redirect_uris' => $app->redirect_uris ?? [],
                    'role' => [
                        'id' => $role->id,
                        'slug' => $role->slug,
                        'name' => $role->name,
                        'description' => $role->description,
                    ],
                ];
            }

            if (!empty($applications)) {
                $result[] = [
                    'id' => $profile->id,
                    'slug' => $profile->slug,
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'is_system' => $profile->is_system,
                    'is_active' => $profile->is_active,
                    'applications_count' => count($applications),
                    'applications' => $applications,
                ];
            }
        }

        return $result;
    }
}
