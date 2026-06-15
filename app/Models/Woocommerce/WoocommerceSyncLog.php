<?php

namespace App\Models\Woocommerce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WoocommerceSyncLog extends Model
{
    protected $table = 'woocommerce_sync_logs';

    protected $fillable = [
        'total_productos',
        'procesados',
        'estado',
        'mensaje_error',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(WoocommerceSyncDetail::class, 'sync_log_id');
    }
}
