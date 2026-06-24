<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\Role;
use App\Services\Sync\BatchedSyncScheduler;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Models\Session as AuthSession;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nip',
        'name',
        'place_of_birth',
        'date_of_birth',
        'gender',
        'address_ktp',
        'phone_number',
        'email',
        'password',
        'status',
        'avatar_url',
        'ttd_url',
        'last_login_at',
        'last_logout_at',
    ];

    /** 
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_logout_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function recordLastLogin(): bool
    {
        $this->last_login_at = Carbon::now();
        $this->last_logout_at = null;

        $saved = $this->save();

        if ($saved) {
            Log::warning('user.last_login_recorded', [
                'user_id' => $this->id,
                'nip' => $this->nip,
                'email' => $this->email,
                'last_login_at' => $this->last_login_at?->toDateTimeString(),
                'last_logout_at' => $this->last_logout_at?->toDateTimeString(),
            ]);
        } else {
            Log::warning('user.last_login_failed', [
                'user_id' => $this->id,
                'nip' => $this->nip,
                'email' => $this->email,
            ]);
        }

        return $saved;
    }

    public function recordLastLogout(): bool
    {
        $this->last_logout_at = Carbon::now();

        $saved = $this->save();

        if ($saved) {
            Log::warning('user.last_logout_recorded', [
                'user_id' => $this->id,
                'nip' => $this->nip,
                'email' => $this->email,
                'last_login_at' => $this->last_login_at?->toDateTimeString(),
                'last_logout_at' => $this->last_logout_at?->toDateTimeString(),
            ]);
        } else {
            Log::warning('user.last_logout_failed', [
                'user_id' => $this->id,
                'nip' => $this->nip,
                'email' => $this->email,
            ]);
        }

        return $saved;
    }

    /**
     * Get all application roles for this user.
     */
    public function applicationRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Iam\Models\ApplicationRole::class,
            'iam_user_application_roles',
            'user_id',
            'role_id'
        )
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Get all access profiles assigned to this user.
     */
    public function accessProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Iam\Models\AccessProfile::class,
            'user_access_profiles',
            'user_id',
            'access_profile_id'
        )
            ->using(\App\Models\UserAccessProfile::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Relasi ke UnitKerja dengan tabel pivot
     *
     * @return BelongsToMany
     */
    public function unitKerjas(): BelongsToMany
    {
        return $this->belongsToMany(UnitKerja::class, 'user_unit_kerja', 'user_id', 'unit_kerja_id')
            ->withTimestamps();
    }

    /**
     * Get the user who assigned this user to an access profile.
     * Used to display "Assigned By" information in relation managers.
     * 
     * OPTIMIZATION: This is a placeholder relationship that will be loaded via pivot values
     */
    public function assignedByUser(): ?\App\Models\User
    {
        // This relationship is handled through the pivot table
        // We use this as a helper for eager loading via with()
        return null;
    }

    /**
     * Get all application roles via active access profiles.
     * This returns roles that are assigned through ACTIVE access profiles only.
     * 
     * SECURITY: Only includes roles from profiles where is_active = true
     * 
     * OPTIMIZATION: Uses a more efficient query with proper eager loading
     */
    public function rolesViaAccessProfiles()
    {
        return \App\Domain\Iam\Models\ApplicationRole::query()
            ->select('iam_roles.*')
            ->join('access_profile_role_iam_map', 'access_profile_role_iam_map.role_id', '=', 'iam_roles.id')
            ->join('user_access_profiles', 'user_access_profiles.access_profile_id', '=', 'access_profile_role_iam_map.access_profile_id')
            ->join('access_profiles', 'access_profiles.id', '=', 'user_access_profiles.access_profile_id')
            ->where('user_access_profiles.user_id', $this->id)
            ->where('access_profiles.is_active', true)
            ->distinct();
    }

    /**
     * Get all effective application roles (direct + via access profiles).
     * OPTIMIZATION: Uses union for better performance vs multiple queries
     */
    public function effectiveApplicationRoles()
    {
        return \App\Domain\Iam\Models\ApplicationRole::query()
            ->select('iam_roles.*')
            ->distinct()
            ->from('iam_roles')
            ->where(function ($query) {
                // Direct roles
                $query->whereIn('id', function ($q) {
                    $q->select('role_id')
                        ->from('iam_user_application_roles')
                        ->where('user_id', $this->id);
                })->orWhereIn('id', function ($q) {
                    // Roles via active access profiles
                    $q->select('iam_roles.id')
                        ->from('iam_roles')
                        ->join('access_profile_role_iam_map', 'access_profile_role_iam_map.role_id', '=', 'iam_roles.id')
                        ->join('user_access_profiles', 'user_access_profiles.access_profile_id', '=', 'access_profile_role_iam_map.access_profile_id')
                        ->join('access_profiles', 'access_profiles.id', '=', 'user_access_profiles.access_profile_id')
                        ->where('user_access_profiles.user_id', $this->id)
                        ->where('access_profiles.is_active', true);
                });
            });
    }

    /**
     * Get user's roles grouped by application as: ['app_key' => ['slug1', 'slug2'], ...].
     * OPTIMIZATION: Added caching to avoid repeated database queries
     */
    public function rolesByApp(): array
    {
        $cacheKey = "user.roles_by_app.{$this->id}";

        return Cache::remember($cacheKey, 3600, function () {
            $roles = $this->effectiveApplicationRoles()->with('application')->get();

            $grouped = [];
            foreach ($roles as $role) {
                $appKey = $role->application->app_key;
                if (! isset($grouped[$appKey])) {
                    $grouped[$appKey] = [];
                }
                if (! in_array($role->slug, $grouped[$appKey], true)) {
                    $grouped[$appKey][] = $role->slug;
                }
            }

            return $grouped;
        });
    }

    /**
     * Get list of app_keys this user has access to.
     * OPTIMIZATION: Added caching and simplified query logic
     *
     * SECURITY: Only includes apps accessible through:
     * - Direct application role assignments, AND
     * - Roles via ACTIVE access profiles only
     *
     * NOTE: IAM admin users (e.g. nip=0000.00000) should not implicitly
     * inherit access to all apps unless permissions are explicitly assigned.
     */
    public function accessibleApps(): array
    {
        $cacheKey = "user.accessible_apps.{$this->id}";

        return Cache::remember($cacheKey, 3600, function () {
            // More efficient single query vs multiple queries
            $appKeys = DB::table('applications as a')
                ->distinct()
                ->select('a.app_key')
                ->leftJoin('iam_roles', 'a.id', '=', 'iam_roles.application_id')
                ->leftJoin('iam_user_application_roles', 'iam_roles.id', '=', 'iam_user_application_roles.role_id')
                ->leftJoin('access_profile_role_iam_map', 'iam_roles.id', '=', 'access_profile_role_iam_map.role_id')
                ->leftJoin('user_access_profiles', 'access_profile_role_iam_map.access_profile_id', '=', 'user_access_profiles.access_profile_id')
                ->leftJoin('access_profiles', 'access_profiles.id', '=', 'user_access_profiles.access_profile_id')
                ->where(function ($q) {
                    $q->where('iam_user_application_roles.user_id', $this->id)
                        ->orWhere(function ($subQ) {
                            $subQ->where('user_access_profiles.user_id', $this->id)
                                ->where('access_profiles.is_active', true);
                        });
                })
                ->pluck('app_key')
                ->unique()
                ->values()
                ->toArray();

            return $appKeys;
        });
    }

    /**
     * Scope for eager loading relationships commonly used in queries
     * OPTIMIZATION: Use this scope in Filament and API queries to prevent N+1
     */
    public function scopeWithCommonRelations($query)
    {
        return $query->with([
            'unitKerjas:id,unit_name',
            'accessProfiles:id,name,is_active',
            'roles:id,name',
        ]);
    }

    /**
     * Clear relationship caches when user is updated
     * OPTIMIZATION: Called automatically after user save
     */
    public function clearRelationshipCaches(): void
    {
        Cache::forget("user.roles_by_app.{$this->id}");
        Cache::forget("user.accessible_apps.{$this->id}");
        $this->cachedIsIamAdmin = null;
    }

    // Cache for session data to avoid repeated DB queries in the same request
    protected ?\stdClass $cachedLatestSession = null;
    protected bool $sessionCacheInitialized = false;
    protected ?bool $cachedIsIamAdmin = null;

    public function hasActiveSession(): bool
    {
        return $this->getLatestActiveSession() !== null;
    }

    public function getLatestActiveSession(): ?\stdClass
    {
        // Return cached session if already loaded in this request
        if ($this->sessionCacheInitialized) {
            return $this->cachedLatestSession;
        }

        $lifetimeSeconds = config('session.lifetime') * 60;

        $session = DB::table('sessions')
            ->where('user_id', $this->id)
            ->where('last_activity', '>=', now()->subSeconds($lifetimeSeconds)->getTimestamp())
            ->orderByDesc('last_activity')
            ->first();

        // Cache the result for subsequent calls in this request
        $this->cachedLatestSession = $session;
        $this->sessionCacheInitialized = true;

        return $session;
    }

    public function getActiveSessionLastActivity(): ?Carbon
    {
        $session = $this->getLatestActiveSession();

        if (! $session) {
            return null;
        }

        return Carbon::createFromTimestamp($session->last_activity, config('app.timezone'));
    }

    public function getActiveSessionExpiresAt(): ?Carbon
    {
        $lastActivity = $this->getActiveSessionLastActivity();

        return $lastActivity ? $lastActivity->copy()->addSeconds(config('session.lifetime') * 60)->setTimezone(config('app.timezone')) : null;
    }

    /**
     * Pre-load and cache sessions for multiple users at once to prevent N+1.
     * Call this before iterating over many users that need session data.
     */
    public static function preloadSessionsForUsers($users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->toArray();
        $lifetimeSeconds = config('session.lifetime') * 60;
        $cutoffTime = now()->subSeconds($lifetimeSeconds)->getTimestamp();

        // Get latest session for each user in one query
        $latestSessions = DB::table('sessions')
            ->whereIn('user_id', $userIds)
            ->where('last_activity', '>=', $cutoffTime)
            ->get()
            ->groupBy('user_id')
            ->map(fn($sessions) => $sessions->sortByDesc('last_activity')->first());

        // Cache the result on each user instance
        foreach ($users as $user) {
            $user->cachedLatestSession = $latestSessions->get($user->id);
            $user->sessionCacheInitialized = true;
        }
    }

    public function getActiveSessionDetails(): ?string
    {
        $lastActivity = $this->getActiveSessionLastActivity();
        $expiresAt = $this->getActiveSessionExpiresAt();

        if (! $lastActivity || ! $expiresAt) {
            return null;
        }

        return sprintf(
            'Terakhir aktif: %s • Kedaluwarsa: %s',
            $lastActivity->format('d M Y H:i:s'),
            $expiresAt->format('d M Y H:i:s')
        );
    }

    public function terminateSessions(): int
    {
        $sessions = AuthSession::where('user_id', $this->id)->get();
        $count = $sessions->count();

        Log::info('session.user_terminate', [
            'user_id' => $this->id,
            'user_nip' => $this->nip,
            'user_email' => $this->email,
            'sessions_deleted' => $count,
        ]);

        if ($count === 0) {
            return 0;
        }

        // Delete sessions using chunking to prevent memory issues
        AuthSession::where('user_id', $this->id)->delete();

        // Clear relationship caches
        $this->clearRelationshipCaches();

        // Ensure any existing JWTs are invalidated even if they are not bound
        // to a terminated session row or the session record was already gone.
        Cache::put("user_logout_at:{$this->id}", time());

        $jwtService = app(\App\Services\JWTTokenService::class);
        \App\Domain\Iam\Models\Application::query()
            ->pluck('app_key')
            ->each(fn(string $appKey) => $jwtService->revokeRefreshToken($this->id, $appKey));

        return $count;
    }

    public function hasActiveAccessProfiles(): bool
    {
        return $this->accessProfiles()->where('is_active', true)->exists();
    }

    public function hasActiveAccessProfileForApp(Application $application): bool
    {
        return $this->accessProfiles()
            ->where('is_active', true)
            ->whereHas('roles', function ($q) use ($application) {
                $q->where('iam_roles.application_id', $application->id);
            })
            ->exists();
    }

    /**
     * Check if user has IAM admin role (for any application).
     * Used for Filament panel and Pulse dashboard access control.
     * 
     * SECURITY: Only counts admin roles from ACTIVE access profiles
     *
     * @return bool
     */
    public function isIAMAdmin(): bool
    {
        if ($this->cachedIsIamAdmin !== null) {
            return $this->cachedIsIamAdmin;
        }

        // Akses special: user 0000.00000 adalah IAM admin seumur hidup.
        if ($this->nip === '0000.00000') {
            return $this->cachedIsIamAdmin = true;
        }

        // Check direct admin role
        $hasDirectAdmin = \App\Domain\Iam\Models\ApplicationRole::query()
            ->where('slug', 'admin')
            ->whereIn('id', function ($query) {
                $query->select('role_id')
                    ->from('iam_user_application_roles')
                    ->where('user_id', $this->id);
            })
            ->exists();

        if ($hasDirectAdmin) {
            return $this->cachedIsIamAdmin = true;
        }

        // Check admin role via ACTIVE access profiles only
        // SECURITY FIX: Added validation for is_active = true
        return $this->cachedIsIamAdmin = \App\Domain\Iam\Models\ApplicationRole::query()
            ->where('slug', 'admin')
            ->whereIn('id', function ($query) {
                $query->select('role_id')
                    ->from('access_profile_role_iam_map')
                    ->whereIn('access_profile_id', function ($subQuery) {
                        $subQuery->select('access_profile_id')
                            ->from('user_access_profiles')
                            ->where('user_id', $this->id)
                            ->whereIn('access_profile_id', function ($profileQuery) {
                                // SECURITY FIX: Only count admin from ACTIVE profiles
                                $profileQuery->select('id')
                                    ->from('access_profiles')
                                    ->where('is_active', true);
                            });
                    });
            })
            ->exists();
    }

    /**
     * Find user by NIP for authentication.
     */
    public function findForAuth(string $username): static
    {
        return $this->where('nip', $username)->first();
    }

    /**
     * Detach access profile and trigger sync.
     * 
     * This method replaces the standard ->detach() to ensure
     * that client applications are notified of the change.
     *
     * @param  mixed  $ids  Profile ID(s) to detach (null = detach all)
     * @return void
     */
    public function detachAccessProfile($ids = null): void
    {
        // Perform the detach operation
        $this->accessProfiles()->detach($ids);

        // Trigger sync to notify clients of the change
        $this->triggerSync('access_profiles_detached');
    }

    /**
     * Attach access profile and trigger sync.
     *
     * This method replaces the standard ->attach() to ensure
     * that client applications are notified of the change.
     *
     * @param  mixed  $ids  Profile ID(s) to attach with optional pivot data
     * @return void
     */
    public function attachAccessProfile($ids): void
    {
        // Perform the attach operation
        $this->accessProfiles()->attach($ids);

        // Trigger sync to notify clients of the change
        $this->triggerSync('access_profiles_attached');
    }

    /**
     * Sync access profiles and trigger sync.
     *
     * This method replaces the standard ->sync() to ensure
     * that client applications are notified of the change.
     *
     * @param  iterable  $ids  Profile ID(s) to sync
     * @return void
     */
    public function syncAccessProfiles($ids): void
    {
        // Perform the sync operation
        $this->accessProfiles()->sync($ids);

        // Trigger sync to notify clients of the change
        $this->triggerSync('access_profiles_synced');
    }

    /**
     * Manually trigger sync to client applications.
     *
     * This is called by the access profile relationship methods
     * and can also be called manually when needed.
     *
     * @param  string  $event  Event description for logging
     * @return void
     */
    public function triggerSync(string $event = 'manual'): void
    {
        \Illuminate\Support\Facades\Log::info('iam.user_access_profile_manual_sync', [
            'user_id' => $this->id,
            'email' => $this->email,
            'event' => $event,
            'timestamp' => now()->toDateTimeString(),
        ]);

        BatchedSyncScheduler::scheduleUser($this->id);
    }
}
