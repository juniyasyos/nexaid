<?php

namespace App\Domain\Iam\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ApplicationRole extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'iam_roles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'slug',
        'name',
        'description',
        'is_system',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Get the application this role belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get all users that have this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'iam_user_application_roles',
            'role_id',
            'user_id'
        )
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Get all access profiles that include this role.
     */
    public function accessProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            AccessProfile::class,
            'access_profile_role_iam_map',
            'role_id',
            'access_profile_id'
        )
            ->withTimestamps();
    }

    /**
     * Scope to find role by slug for a specific application.
     */
    public function scopeForApplication($query, int $applicationId)
    {
        return $query->where('application_id', $applicationId);
    }

    /**
     * Scope to find role by slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Check if this is a protected system role.
     */
    public function isSystemRole(): bool
    {
        return $this->is_system === true;
    }

    /**
     * Get role identifier as "app_key:slug".
     */
    public function getIdentifierAttribute(): string
    {
        return $this->application->app_key.':'.$this->slug;
    }

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            // If slug not provided, generate from name
            if (empty($role->slug) && ! empty($role->name)) {
                $base = Str::slug($role->name);
                $slug = $base;
                $i = 1;

                while (self::where('application_id', $role->application_id)->where('slug', $slug)->exists()) {
                    $i++;
                    $slug = $base . '-' . $i;
                }

                $role->slug = $slug;
            }
        });
    }
}
