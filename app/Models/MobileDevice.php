<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MobileDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_uuid',
        'nombre',
        'plataforma',
        'app_version',
        'last_seen_at',
        'revocado_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncState(): HasOne
    {
        return $this->hasOne(MobileUserSyncState::class);
    }
}
