<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RhBancoTiempo extends Model
{
    use SoftDeletes;

    public const ESTADO_ACTIVA  = 'activa';
    public const ESTADO_SALDADA = 'saldada';

    protected $table = 'rh_banco_tiempo';

    protected $fillable = [
        'uuid',
        'folio',
        'rh_colaborador_id',
        'horas_pendientes',
        'origen_deuda',
        'estado',
        'fecha_acuerdo',
        'fecha_devolucion',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'horas_pendientes' => 'decimal:2',
            'fecha_acuerdo'    => 'date',
            'fecha_devolucion' => 'date',
        ];
    }

    public function getNombreEstadoAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_SALDADA => 'Saldada',
            default              => 'Activa',
        };
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
