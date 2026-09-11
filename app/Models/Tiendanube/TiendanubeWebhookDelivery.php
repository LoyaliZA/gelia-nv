<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeWebhookDelivery extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_RETRY_PENDING = 'retry_pending';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IGNORED = 'ignored';

    protected $table = 'tiendanube_webhook_deliveries';

    protected $fillable = [
        'store_id',
        'event',
        'resource_id',
        'payload',
        'payload_hash',
        'hmac_valid',
        'status',
        'error',
        'deferred_reason',
        'attempts',
        'next_attempt_at',
        'lease_token',
        'lease_expires_at',
        'processing_started_at',
        'processed_at',
        'failed_at',
        'retried_by_user_id',
        'retried_at',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'payload' => 'array',
            'hmac_valid' => 'boolean',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'retried_at' => 'datetime',
        ];
    }

    public function retriedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'retried_by_user_id');
    }

    public function puedeReintentar(): bool
    {
        return in_array($this->status, [
            self::STATUS_RECEIVED,
            self::STATUS_QUEUED,
            self::STATUS_RETRY_PENDING,
            self::STATUS_FAILED,
        ], true);
    }
}
