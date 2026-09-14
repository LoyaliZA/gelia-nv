<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioLoteSimulacion extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_CURSO = 'en_curso';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_ERROR = 'error';

    protected $table = 'tiendanube_precio_lote_simulaciones';

    protected $fillable = [
        'revision_id',
        'estado',
        'total',
        'procesados',
        'error',
        'lease_token',
        'lease_expires_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_id' => 'integer',
            'total' => 'integer',
            'procesados' => 'integer',
            'lease_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLoteRevision::class, 'revision_id');
    }

    public function porcentaje(): int
    {
        if ($this->total <= 0) {
            return $this->estado === self::ESTADO_COMPLETADA ? 100 : 0;
        }

        return (int) min(100, (int) floor(($this->procesados / $this->total) * 100));
    }
}
