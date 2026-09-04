<?php

namespace App\Models\PuntoVenta;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoPdvEvento extends Model
{
    public const TIPO_ALTA = 'turno.alta';

    public const TIPO_ASIGNADO = 'turno.asignado';

    public const TIPO_BAJA_COLA = 'turno.baja_cola';

    public const TIPO_ATENCION_CERRADA = 'atencion.cerrada';

    public const TIPO_TRANSFERIDO = 'turno.transferido';

    public const TIPO_PRORROGA = 'atencion.prorroga';

    public const TIPO_REATENCION = 'turno.reatencion';

    public const TIPO_VENTANA_REATENCION_VENCIDA = 'turno.ventana_reatencion_vencida';

    protected $table = 'pdv_turno_eventos';

    protected $fillable = [
        'turno_id',
        'atencion_id',
        'tipo_evento',
        'estado_anterior',
        'estado_nuevo',
        'actor_id',
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

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoPdv::class, 'turno_id');
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(TurnoPdvAtencion::class, 'atencion_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
