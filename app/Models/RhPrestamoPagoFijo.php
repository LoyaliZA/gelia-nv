<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RhPrestamoPagoFijo extends Model
{
    use SoftDeletes;

    protected $table = 'rh_prestamos_pagos_fijos';

    public const MODALIDAD_RECURRENTE = 'recurrente';

    public const MODALIDAD_UNICA_VEZ = 'unica_vez';

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_PAUSADO = 'pausado';

    public const ESTADO_LIQUIDADO = 'liquidado';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'uuid',
        'folio',
        'rh_colaborador_id',
        'concepto',
        'monto_cuota',
        'num_pagos_total',
        'pagos_realizados',
        'modalidad',
        'observaciones',
        'fecha_ejecucion_programada',
        'fecha_inicio',
        'estado',
        'ultimo_periodo_generado',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'monto_cuota' => 'decimal:2',
            'num_pagos_total' => 'integer',
            'pagos_realizados' => 'integer',
            'fecha_ejecucion_programada' => 'date',
            'fecha_inicio' => 'date',
            'ultimo_periodo_generado' => 'date',
        ];
    }

    protected function pagosRestantes(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->num_pagos_total === null) {
                return null;
            }

            return max(0, $this->num_pagos_total - $this->pagos_realizados);
        });
    }

    protected function montoTotalEstimado(): Attribute
    {
        return Attribute::get(function (): ?float {
            if ($this->num_pagos_total === null) {
                return null;
            }

            return round((float) $this->monto_cuota * $this->num_pagos_total, 2);
        });
    }

    public function estaLiquidado(): bool
    {
        return $this->estado === self::ESTADO_LIQUIDADO;
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function deducciones(): HasMany
    {
        return $this->hasMany(RhDeduccion::class, 'rh_prestamo_pago_fijo_id');
    }
}
