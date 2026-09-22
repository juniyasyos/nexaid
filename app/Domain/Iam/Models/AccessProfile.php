<?php

namespace App\Domain\Iam\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AccessProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'key_hash',
        'slug',
        'name',
        'description',
        'is_system',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    /**
     * Scope: hanya profile yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relation many-to-many with ApplicationRole.
     */
    public function roles()
    {
        return $this->belongsToMany(
            ApplicationRole::class,
            'access_profile_role_iam_map',
            'access_profile_id',
            'role_id'
        )
            ->withTimestamps();
    }

    /**
     * Resolve the model factory.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AccessProfileFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $accessProfile): void {
            $accessProfile->key_hash = self::generateKeyHash();

            if (empty($accessProfile->slug) && filled($accessProfile->name)) {
                $accessProfile->slug = self::generateUniqueSlug($accessProfile->name);
            }
        });

        static::updating(function (self $accessProfile): void {
            if ($accessProfile->isDirty('key_hash')) {
                $accessProfile->key_hash = $accessProfile->getOriginal('key_hash');
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_');
        if (empty($base)) {
            $base = 'profile';
        }
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $i++;
            $slug = $base . '_' . $i;
        }

        return $slug;
    }

    public static function generateKeyHash(): string
    {
        do {
            $keyHash = hash('sha256', (string) Str::ulid());
        } while (static::where('key_hash', $keyHash)->exists());

        return $keyHash;
    }

    /**
     * Relation many-to-many with User.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_access_profiles')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
