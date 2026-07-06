<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RhDeduccion extends Model
{
    use SoftDeletes;

    protected $table = 'rh_deducciones';

    public const ORIGEN_NOMINA = 'nomina';

    public const ORIGEN_COMISIONES = 'comisiones';

    public const ESTADO_PENDIENTE_NOMINA = 'pendiente_nomina';

    public const ESTADO_PENDIENTE_COMISION = 'pendiente_comision';

    public const ESTADO_APLICADO = 'aplicado';

    protected $fillable = [
        'uuid',
        'folio',
        'fecha_ocurrencia',
        'rh_colaborador_id',
        'rh_prestamo_pago_fijo_id',
        'numero_cuota',
        'catalogo_regla_incidencia_id',
        'producto_id',
        'producto_sku_snapshot',
        'monto_deduccion_base',
        'factor_multiplicador',
        'total_parcial',
        'monto_total_final',
        'deduccion_salario_base',
        'deduccion_bono_puntualidad',
        'deduccion_bono_productividad',
        'total_deduccion',
        'origen_deduccion',
        'descripcion_detallada',
        'fecha_deduccion_nomina',
        'fecha_aplicacion_deduccion',
        'estado_deduccion',
        'salario_diario_snapshot',
        'bono_puntualidad_diario_snapshot',
        'bono_productividad_diario_snapshot',
        'factor_puntualidad_snapshot',
        'factor_productividad_snapshot',
        'aplica_deduccion_salario_snapshot',
        'regla_nombre_snapshot',
        'regla_comportamiento_snapshot',
        'departamento_snapshot',
        'area_snapshot',
        'registrado_por_id',
        'firma_gerente_path',
        'firma_colaborador_path',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ocurrencia' => 'date',
            'fecha_deduccion_nomina' => 'date',
            'fecha_aplicacion_deduccion' => 'date',
            'monto_deduccion_base' => 'decimal:2',
            'factor_multiplicador' => 'decimal:2',
            'total_parcial' => 'decimal:2',
            'monto_total_final' => 'decimal:2',
            'deduccion_salario_base' => 'decimal:2',
            'deduccion_bono_puntualidad' => 'decimal:2',
            'deduccion_bono_productividad' => 'decimal:2',
            'total_deduccion' => 'decimal:2',
            'salario_diario_snapshot' => 'decimal:2',
            'bono_puntualidad_diario_snapshot' => 'decimal:2',
            'bono_productividad_diario_snapshot' => 'decimal:2',
            'factor_puntualidad_snapshot' => 'decimal:2',
            'factor_productividad_snapshot' => 'decimal:2',
            'aplica_deduccion_salario_snapshot' => 'boolean',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function prestamoPagoFijo(): BelongsTo
    {
        return $this->belongsTo(RhPrestamoPagoFijo::class, 'rh_prestamo_pago_fijo_id');
    }

    public function reglaIncidencia(): BelongsTo
    {
        return $this->belongsTo(CatalogoReglaIncidencia::class, 'catalogo_regla_incidencia_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function comisionAuditor(): HasOne
    {
        return $this->hasOne(RhComisionAuditor::class, 'rh_deduccion_id');
    }

    public function movimientoComisionColaborador(): HasOne
    {
        return $this->hasOne(RhMovimientoComisionColaborador::class, 'rh_deduccion_id');
    }
}
