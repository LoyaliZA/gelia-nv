<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MobileBootstrapSnapshot extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'id',
        'user_id',
        'scope_version',
        'publish_seq_at_start',
        'total_items',
        'max_cliente_id',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'publish_seq_at_start' => 'integer',
            'total_items' => 'integer',
            'max_cliente_id' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $snapshot) {
            if (! $snapshot->id) {
                $snapshot->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MobileBootstrapSnapshotItem::class, 'snapshot_id');
    }

    public function estaVigente(): bool
    {
        return in_array($this->status, [self::STATUS_BUILDING, self::STATUS_READY], true)
            && $this->expires_at->isFuture();
    }
}
