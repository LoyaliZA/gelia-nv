<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeSyncRecursoVisto extends Model
{
    public const TIPO_CATEGORIA = 'categoria';

    public const TIPO_PRODUCTO = 'producto';

    protected $table = 'tiendanube_sync_recursos_vistos';

    protected $fillable = [
        'sync_log_id',
        'tipo',
        'recurso_id',
    ];

    protected function casts(): array
    {
        return [
            'recurso_id' => 'integer',
        ];
    }

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(TiendanubeSyncLog::class, 'sync_log_id');
    }
}
