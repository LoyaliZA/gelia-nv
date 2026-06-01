<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoReglaIncidencia extends Model
{
    public const COMPORTAMIENTO_COBRO_FIJO = 'cobro_fijo';

    public const COMPORTAMIENTO_COBRO_COSTO_PRODUCTO = 'cobro_costo_producto';

    public const COMPORTAMIENTO_COBRO_PRECIO_VENTA = 'cobro_precio_venta_producto';

    public const COMPORTAMIENTO_CANCELACION_BONO = 'cancelacion_bono_especifico';

    public const COMPORTAMIENTO_DEDUCCION_NOMINA = 'deduccion_nomina';

    public const CATEGORIA_FALTA = 'falta';

    public const CATEGORIA_RETARDO = 'retardo';

    public const CATEGORIA_OPERATIVA = 'operativa';

    public const COMPORTAMIENTOS = [
        self::COMPORTAMIENTO_COBRO_FIJO => 'Cobro Fijo',
        self::COMPORTAMIENTO_COBRO_COSTO_PRODUCTO => 'Cobro por Costo de Producto',
        self::COMPORTAMIENTO_COBRO_PRECIO_VENTA => 'Cobro por Precio de Venta de Producto',
        self::COMPORTAMIENTO_CANCELACION_BONO => 'Cancelación de Bono Específico',
        self::COMPORTAMIENTO_DEDUCCION_NOMINA => 'Deducción Nómina (Faltas/Retardos)',
    ];

    public const CATEGORIAS = [
        self::CATEGORIA_FALTA => 'Falta',
        self::CATEGORIA_RETARDO => 'Retardo',
        self::CATEGORIA_OPERATIVA => 'Operativa',
    ];

    protected $table = 'catalogo_reglas_incidencia';

    protected $fillable = [
        'uuid',
        'folio',
        'nombre',
        'categoria',
        'tipo_comportamiento',
        'monto_fijo',
        'catalogo_bono_id',
        'factor_penalizacion_puntualidad',
        'factor_penalizacion_productividad',
        'aplica_deduccion_salario_base',
        'recompensa_auditor_activa',
        'monto_recompensa_auditor',
        'catalogo_tipo_falta_legacy_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'monto_fijo' => 'decimal:2',
            'factor_penalizacion_puntualidad' => 'decimal:2',
            'factor_penalizacion_productividad' => 'decimal:2',
            'aplica_deduccion_salario_base' => 'boolean',
            'recompensa_auditor_activa' => 'boolean',
            'monto_recompensa_auditor' => 'decimal:2',
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

    public function deducciones(): HasMany
    {
        return $this->hasMany(RhDeduccion::class, 'catalogo_regla_incidencia_id');
    }

    public function requiereProducto(): bool
    {
        return in_array($this->tipo_comportamiento, [
            self::COMPORTAMIENTO_COBRO_COSTO_PRODUCTO,
            self::COMPORTAMIENTO_COBRO_PRECIO_VENTA,
        ], true);
    }

    public function esDeduccionNomina(): bool
    {
        return $this->tipo_comportamiento === self::COMPORTAMIENTO_DEDUCCION_NOMINA
            || in_array($this->categoria, [self::CATEGORIA_FALTA, self::CATEGORIA_RETARDO], true);
    }

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

        return true;
    }

    public function visibleParaUsuario(User $usuario): bool
    {
        if (!$this->activo) {
            return false;
        }

        $deptVis = $this->departamentosVisibilidad()->pluck('departamentos.id');
        if ($deptVis->isNotEmpty()) {
            $deptUsuario = $usuario->departamentos()->pluck('departamentos.id');
            if ($deptUsuario->intersect($deptVis)->isEmpty()) {
                return false;
            }
        }

        $areaVis = $this->areasVisibilidad()->pluck('areas.id');
        if ($areaVis->isNotEmpty()) {
            $areasUsuario = $usuario->areas()->pluck('areas.id');
            if ($areasUsuario->intersect($areaVis)->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    public function disponiblePara(RhColaborador $colaborador, User $usuario): bool
    {
        return $this->aplicaParaColaborador($colaborador) && $this->visibleParaUsuario($usuario);
    }
}
