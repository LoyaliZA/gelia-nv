<?php

namespace App\Models\Comercial;

use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitaClienteProgramada extends Model
{
    public const TIPO_HORA_SIN = 'sin_hora';

    public const TIPO_HORA_EXACTA = 'exacta';

    public const TIPO_HORA_RANGO = 'rango';

    public const INTENCION_CONFIRMO = 'confirmo_asistencia';

    public const INTENCION_POSIBLE = 'posible_asistencia';

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_ASISTIO = 'asistio';

    public const ESTADO_NO_ASISTIO = 'no_asistio';

    protected $table = 'visita_cliente_programadas';

    protected $fillable = [
        'cliente_id',
        'sucursal_id',
        'fecha',
        'tipo_hora',
        'hora_exacta',
        'hora_inicio',
        'hora_fin',
        'intencion',
        'estado',
        'registrado_por_user_id',
        'llegada_confirmada_at',
        'llegada_confirmada_por_user_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'llegada_confirmada_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function llegadaConfirmadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'llegada_confirmada_por_user_id');
    }
}
