<?php

namespace App\Models\Woocommerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoocommerceSyncDetail extends Model
{
    protected $table = 'woocommerce_sync_details';

    protected $fillable = [
        'sync_log_id',
        'sku',
        'precio_anterior_normal',
        'precio_nuevo_normal',
        'precio_anterior_rebajado',
        'precio_nuevo_rebajado',
        'estado',
        'mensaje',
    ];

    protected $casts = [
        'precio_anterior_normal' => 'float',
        'precio_nuevo_normal' => 'float',
        'precio_anterior_rebajado' => 'float',
        'precio_nuevo_rebajado' => 'float',
    ];

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(WoocommerceSyncLog::class, 'sync_log_id');
    }
}
