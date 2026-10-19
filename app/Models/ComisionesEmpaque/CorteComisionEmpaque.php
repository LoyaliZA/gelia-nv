<?php

namespace App\Models\ComisionesEmpaque;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorteComisionEmpaque extends Model
{
    public const BORRADOR = 'borrador';

    public const CONFIRMADO = 'confirmado';

    public const DESCARTADO = 'descartado';

    protected $table = 'comisiones_empaque_cortes';

    protected $fillable = [
        'folio', 'area_id', 'estado', 'limite_desde', 'limite_hasta', 'incluye_tardias', 'cajas', 'total_centavos',
        'hash_calculo', 'simulacion', 'confirmado_at', 'confirmado_por_id', 'creado_por_id',
    ];

    protected $casts = [
        'limite_desde' => 'datetime',
        'limite_hasta' => 'datetime',
        'confirmado_at' => 'datetime',
        'incluye_tardias' => 'boolean',
        'cajas' => 'integer',
        'total_centavos' => 'integer',
        'simulacion' => 'array',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaComisionEmpaque::class, 'area_id');
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(CorteUnidadComisionEmpaque::class, 'corte_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionComisionEmpaque::class, 'corte_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteComisionEmpaque::class, 'corte_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por_id');
    }

    public function estadoPago(): string
    {
        $asignaciones = $this->relationLoaded('asignaciones')
            ? $this->asignaciones
            : $this->asignaciones()->with('ajustes')->get();

        if ($asignaciones->isEmpty()) {
            return 'pendiente';
        }

        $pagado = 0;
        $pendiente = 0;
        foreach ($asignaciones as $asignacion) {
            $pagado += (int) $asignacion->pagado_centavos;
            $pendiente += $asignacion->pendienteCentavos();
        }

        if ($pagado === 0) {
            return 'pendiente';
        }

        return $pendiente === 0 ? 'pagado' : 'parcial';
    }
}
