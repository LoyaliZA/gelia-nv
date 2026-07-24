<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;

class TiendanubeWebhookDelivery extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'payload' => 'array',
            'hmac_valid' => 'boolean',
        ];
    }

    public function markProcessed(): void
    {
        $this->forceFill(['status' => 'processed', 'error' => null])->save();
    }

    public function markIgnored(?string $reason = null): void
    {
        $this->forceFill(['status' => 'ignored', 'error' => $reason])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['status' => 'failed', 'error' => $error])->save();
    }
}
