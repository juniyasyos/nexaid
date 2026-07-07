<?php

namespace App\Domain\Iam\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class SsoAccessLog extends Model
{
    protected $table = 'sso_access_logs';
    
    public $timestamps = false; // We only have accessed_at

    protected $fillable = [
        'user_id',
        'application_id',
        'access_profile_id',
        'role_id',
        'ip_address',
        'session_id',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function accessProfile(): BelongsTo
    {
        return $this->belongsTo(AccessProfile::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ApplicationRole::class, 'role_id');
    }
}
