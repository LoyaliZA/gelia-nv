<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileUserSyncState extends Model
{
    protected $table = 'mobile_user_sync_state';

    protected $fillable = [
        'mobile_device_id',
        'scope_version',
        'cursor_seq',
        'bootstrap_snapshot_id',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'cursor_seq' => 'integer',
            'last_sync_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(MobileDevice::class, 'mobile_device_id');
    }
}
