<?php

namespace App\Domain\Users\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Resolves user session state with proper timezone handling.
 * 
 * Consolidates all session state logic that was previously duplicated
 * 4x in UsersTable column callbacks.
 * 
 * Handles:
 * - Session window calculation (start/end timestamps)
 * - Session status determination (Online/Offline/Never)
 * - Status color mapping for UI
 * - User-friendly descriptions
 * - Time remaining calculations
 * - Tooltip formatting
 */
class UserSessionStateResolver
{
    /**
     * In-memory cache of resolved session state per user.
     *
     * @var array<string, array{window: array{start: Carbon, end: Carbon}|null, status: string, color: string, description: string|null, tooltip: string|null}>
     */
    private array $resolved = [];

    /**
     * Get the session window for a user (start and end timestamps).
     * 
     * @param User $user
     * @return array{start: Carbon, end: Carbon}|null Session window or null if no active/recent session
     */
    public function getSessionWindow(User $user): ?array
    {
        return $this->resolve($user)['window'];
    }

    /**
     * Get the session status for display.
     * 
     * @param User $user
     * @return string 'Online' | 'Offline' | 'Tidak login' (Never)
     */
    public function getStatus(User $user): string
    {
        return $this->resolve($user)['status'];
    }

    /**
     * Get the color for status badge display.
     * 
     * @param User $user
     * @return string 'success' | 'warning' | 'secondary'
     */
    public function getStatusColor(User $user): string
    {
        return $this->resolve($user)['color'];
    }

    /**
     * Get detailed description for the status.
     * 
     * Examples:
     * - "Login sejak 09:30 • berakhir 10:30 • tersisa 45 menit"
     * - "Sesi sudah berakhir pada 15:30"
     * - "Pengguna belum pernah login"
     * 
     * @param User $user
     * @return string|null User-friendly description
     */
    public function getDescription(User $user): ?string
    {
        return $this->resolve($user)['description'];
    }

    /**
     * Get tooltip for the session status.
     * 
     * Example: "09:30 - 10:30 (WIB)"
     * 
     * @param User $user
     * @return string|null Tooltip text or null if no session
     */
    public function getTooltip(User $user): ?string
    {
        return $this->resolve($user)['tooltip'];
    }

    /**
     * Resolve session state once and reuse it for every UI callback.
     *
     * @param User $user
     * @return array{window: array{start: Carbon, end: Carbon}|null, status: string, color: string, description: string|null, tooltip: string|null}
     */
    private function resolve(User $user): array
    {
        $cacheKey = $this->getCacheKey($user);

        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        $now = $this->getNow();
        $lifetimeSeconds = $this->getSessionLifetimeSeconds();
        $window = null;

        if ($this->hasCachedSession($user)) {
            $start = $this->getCachedSessionStart($user);
            $window = [
                'start' => $start,
                'end' => $start->copy()->addSeconds($lifetimeSeconds),
            ];
        } elseif ($user->last_login_at !== null && $this->isLoginMoreRecent($user)) {
            $start = $user->last_login_at->copy()->setTimezone(config('app.timezone'));
            $window = [
                'start' => $start,
                'end' => $start->copy()->addSeconds($lifetimeSeconds),
            ];
        }

        if ($window === null) {
            $isNeverLoggedIn = $user->last_login_at === null && $user->last_logout_at === null;

            return $this->resolved[$cacheKey] = [
                'window' => null,
                'status' => $isNeverLoggedIn ? 'Tidak login' : 'Offline',
                'color' => $isNeverLoggedIn ? 'secondary' : 'warning',
                'description' => $isNeverLoggedIn ? 'Pengguna belum pernah login' : 'Tidak ada sesi login aktif',
                'tooltip' => 'Tidak ada sesi login aktif',
            ];
        }

        $isActive = $now->between($window['start'], $window['end']);
        $description = $isActive
            ? sprintf(
                'Login sejak %s • berakhir %s • tersisa %s',
                $window['start']->format('H:i'),
                $window['end']->format('H:i'),
                $this->formatTimeRemaining($now->diffInMinutes($window['end'], false)),
            )
            : sprintf('Sesi sudah berakhir pada %s', $window['end']->format('H:i'));

        return $this->resolved[$cacheKey] = [
            'window' => $window,
            'status' => $isActive ? 'Online' : 'Offline',
            'color' => $isActive ? 'success' : 'warning',
            'description' => $description,
            'tooltip' => sprintf('%s - %s (WIB)', $window['start']->format('H:i'), $window['end']->format('H:i')),
        ];
    }

    /**
     * Get time remaining in human-readable format.
     * 
     * Examples:
     * - "2 jam"
     * - "45 menit"
     * - "2 jam 30 menit"
     * - "kurang dari 1 menit"
     * 
     * @param int $minutes Minutes remaining
     * @return string Human-readable time
     */
    public function formatTimeRemaining(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'kurang dari 1 menit';
        }

        if ($minutes < 60) {
            return sprintf('%d menit', $minutes);
        }

        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        if ($mins === 0) {
            return sprintf('%d jam', $hours);
        }

        return sprintf('%d jam %d menit', $hours, $mins);
    }

    /**
     * Check if user has a cached session available.
     * 
     * @param User $user
     * @return bool
     */
    private function hasCachedSession(User $user): bool
    {
        return !empty($user->sessionCacheInitialized) && !empty($user->cachedLatestSession);
    }

    /**
     * Get the start time of the cached session.
     * 
     * @param User $user
     * @return Carbon Session start timestamp
     */
    private function getCachedSessionStart(User $user): Carbon
    {
        return Carbon::createFromTimestamp(
            $user->cachedLatestSession->last_activity,
            config('app.timezone')
        );
    }

    /**
     * Check if last_login_at is more recent than last_logout_at.
     * 
     * @param User $user
     * @return bool
     */
    private function isLoginMoreRecent(User $user): bool
    {
        return $user->last_logout_at === null ||
               $user->last_login_at->greaterThan($user->last_logout_at);
    }

    /**
     * Get the current timestamp in app timezone.
     * 
     * @return Carbon
     */
    private function getNow(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }

    /**
     * Build a stable cache key for the current model instance.
     */
    private function getCacheKey(User $user): string
    {
        return sprintf('%s:%s', $user::class, $user->getKey() ?? spl_object_hash($user));
    }

    /**
     * Get session lifetime in seconds.
     * 
     * @return int
     */
    private function getSessionLifetimeSeconds(): int
    {
        return config('session.lifetime') * 60;
    }
}
