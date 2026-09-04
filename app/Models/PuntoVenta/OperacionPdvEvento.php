<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacionPdvEvento extends Model
{
    public const TIPO_CIERRE_HORARIO = 'jornada.cierre_horario';

    protected $table = 'pdv_operacion_eventos';

    protected $fillable = [
        'sucursal_dia_id',
        'sucursal_id',
        'tipo_evento',
        'ocurrido_at',
        'snapshot_json',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'ocurrido_at' => 'datetime',
            'snapshot_json' => 'array',
        ];
    }

    public function sucursalDia(): BelongsTo
    {
        return $this->belongsTo(SucursalDiaOperacionPdv::class, 'sucursal_dia_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
