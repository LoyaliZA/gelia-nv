<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsperaDisponibilidadPdv extends Model
{
    public const ORIGEN_ATENCION_CERRADA = 'atencion.cerrada';

    public const ORIGEN_PAUSA_FINALIZADA = 'pausa.finalizada';

    protected $table = 'pdv_esperas_disponibilidad';

    protected $fillable = [
        'jornada_id',
        'user_id',
        'sucursal_id',
        'actor_id',
        'origen',
        'cooldown_segundos',
        'disponible_desde_anterior',
        'disponible_desde_nuevo',
        'atencion_id',
        'intervalo_id',
        'ocurrido_at',
    ];

    protected function casts(): array
    {
        return [
            'cooldown_segundos' => 'integer',
            'disponible_desde_anterior' => 'datetime',
            'disponible_desde_nuevo' => 'datetime',
            'ocurrido_at' => 'datetime',
        ];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(JornadaPdv::class, 'jornada_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(TurnoPdvAtencion::class, 'atencion_id');
    }

    public function intervalo(): BelongsTo
    {
        return $this->belongsTo(IntervaloOperativoPdv::class, 'intervalo_id');
    }
}
