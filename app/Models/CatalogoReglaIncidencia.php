<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CatalogoReglaIncidencia extends Model
{
    public const COMPORTAMIENTO_COBRO_FIJO = 'cobro_fijo';

    public const COMPORTAMIENTO_COBRO_COSTO_PRODUCTO = 'cobro_costo_producto';

    public const COMPORTAMIENTO_COBRO_PRECIO_VENTA = 'cobro_precio_venta_producto';

    public const COMPORTAMIENTO_CANCELACION_BONO = 'cancelacion_bono_especifico';

    public const COMPORTAMIENTOS = [
        self::COMPORTAMIENTO_COBRO_FIJO => 'Cobro Fijo',
        self::COMPORTAMIENTO_COBRO_COSTO_PRODUCTO => 'Cobro por Costo de Producto',
        self::COMPORTAMIENTO_COBRO_PRECIO_VENTA => 'Cobro por Precio de Venta de Producto',
        self::COMPORTAMIENTO_CANCELACION_BONO => 'Cancelación de Bono Específico',
    ];

    protected $table = 'catalogo_reglas_incidencia';

    protected $fillable = [
        'uuid',
        'folio',
        'nombre',
        'tipo_comportamiento',
        'monto_fijo',
        'catalogo_bono_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'monto_fijo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function bono(): BelongsTo
    {
        return $this->belongsTo(CatalogoBono::class, 'catalogo_bono_id');
    }

    public function departamentosAplicables(): BelongsToMany
    {
        return $this->belongsToMany(
            Departamento::class,
            'catalogo_regla_incidencia_departamento_aplicable',
            'catalogo_regla_incidencia_id',
            'departamento_id',
        );
    }

    public function areasAplicables(): BelongsToMany
    {
        return $this->belongsToMany(
            Area::class,
            'catalogo_regla_incidencia_area_aplicable',
            'catalogo_regla_incidencia_id',
            'area_id',
        );
    }

    public function departamentosVisibilidad(): BelongsToMany
    {
        return $this->belongsToMany(
            Departamento::class,
            'catalogo_regla_incidencia_departamento_visibilidad',
            'catalogo_regla_incidencia_id',
            'departamento_id',
        );
    }

    public function areasVisibilidad(): BelongsToMany
    {
        return $this->belongsToMany(
            Area::class,
            'catalogo_regla_incidencia_area_visibilidad',
            'catalogo_regla_incidencia_id',
            'area_id',
        );
    }

    /**
     * Sprint 1.6: evaluar si la regla es visible/aplicable para un colaborador según dept/área.
     */
    public function aplicaParaColaborador(RhColaborador $colaborador): bool
    {
        if (!$this->activo) {
            return false;
        }

        $deptAplicables = $this->departamentosAplicables()->pluck('departamentos.id');
        if ($deptAplicables->isNotEmpty() && !$deptAplicables->contains($colaborador->departamento_id)) {
            return false;
        }

        $areaAplicables = $this->areasAplicables()->pluck('areas.id');
        if ($areaAplicables->isNotEmpty() && (!$colaborador->area_id || !$areaAplicables->contains($colaborador->area_id))) {
            return false;
        }

        $deptVis = $this->departamentosVisibilidad()->pluck('departamentos.id');
        if ($deptVis->isNotEmpty() && !$deptVis->contains($colaborador->departamento_id)) {
            return false;
        }

        $areaVis = $this->areasVisibilidad()->pluck('areas.id');
        if ($areaVis->isNotEmpty() && (!$colaborador->area_id || !$areaVis->contains($colaborador->area_id))) {
            return false;
        }

        return true;
    }
}
