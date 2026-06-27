<?php

namespace App\Domain\Iam\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class Application extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Map factory to Database\Factories\ApplicationFactory (non-namespaced).
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\ApplicationFactory::new();
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'applications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'app_key',
        'name',
        'description',
        'enabled',
        'redirect_uris',
        'callback_url',
        'backchannel_url',
        'logout_uri',
        'secret',
        'logo_url',
        'icon',
        'gradient',
        'token_expiry',
        'created_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'redirect_uris' => 'array',
        'enabled' => 'boolean',
        'token_expiry' => 'integer',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * Scope only enabled applications.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Find an application by its key or throw.
     * OPTIMIZATION: Uses cache to prevent repeated database queries
     */
    public static function findByKey(string $key): self
    {
        $normalizedKey = Str::slug($key, '.');
        $cacheKey = "app.{$normalizedKey}";

        return Cache::remember($cacheKey, 3600, function () use ($normalizedKey) {
            return static::where('app_key', $normalizedKey)->firstOrFail();
        });
    }

    /**
     * Clear cache for this application.
     * OPTIMIZATION: Called when application is updated or deleted
     */
    public function clearAppCache(): void
    {
        Cache::forget("app.{$this->app_key}");
    }

    /**
     * Verify application secret.
     */
    public function verifySecret(string $secret): bool
    {
        return hash_equals($this->secret, hash('sha256', $secret));
    }

    /**
     * Hash and set the application secret.
     */
    public function setSecretAttribute(?string $value): void
    {
        if ($value !== null) {
            $this->attributes['secret'] = hash('sha256', $value);
        } else {
            $this->attributes['secret'] = null;
        }
    }

    /**
     * Get the token expiry in seconds.
     *
     * Priority:
     * 1. app-specific value `token_expiry`
     * 2. environment override `IAM_SSO_TOKEN_EXPIRY_SECONDS`
     * 3. fallback default 3600
     */
    public function getTokenExpirySeconds(): int
    {
        return $this->token_expiry
            ?? (int) env('IAM_SSO_TOKEN_EXPIRY_SECONDS', 3600);
    }

    /**
     * Check if a redirect URI is valid for this application.
     */
    public function isValidRedirectUri(string $uri): bool
    {
        if (empty($this->redirect_uris)) {
            return false;
        }

        return in_array($uri, $this->redirect_uris, true);
    }

    /**
     * Derive the client's OP‑initiated logout URI.
     *
     * - If the client uses the `laravel-iam-client` package, it exposes
     *   a public `/iam/logout` endpoint. We derive that automatically
     *   from the application's first `redirect_uris` entry or from the
     *   `callback_url` when available.
     */
    public function getLogoutUriAttribute(): ?string
    {
        // Strict policy: use configured redirect_uris for front-channel logout
        // target. Do not fallback to callback_url to avoid mixing internal
        // container hostnames with browser-accessible URLs.
        if (! empty($this->redirect_uris) && is_array($this->redirect_uris) && count($this->redirect_uris) > 0) {
            $base = rtrim($this->redirect_uris[0], '/');
            return $base . '/iam/logout';
        }

        return null;
    }

    /**
     * Get the creator of this application.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get all roles defined for this application.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(ApplicationRole::class);
    }

    /**
     * Get only system roles for this application.
     */
    public function systemRoles(): HasMany
    {
        return $this->roles()->where('is_system', true);
    }
}
