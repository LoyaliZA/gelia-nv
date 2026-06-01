<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RhIncidencia extends Model
{
    use SoftDeletes;

    protected $table = 'rh_incidencias';

    protected $fillable = [
        'uuid',
        'folio',
        'fecha_ocurrencia',
        'rh_colaborador_id',
        'catalogo_tipo_falta_id',
        'deduccion_salario_base',
        'deduccion_bono_puntualidad',
        'deduccion_bono_productividad',
        'total_deduccion',
        'observaciones',
        'fecha_deduccion_nomina',
        'estado_deduccion',
        'salario_diario_snapshot',
        'bono_puntualidad_diario_snapshot',
        'bono_productividad_diario_snapshot',
        'factor_puntualidad_snapshot',
        'factor_productividad_snapshot',
        'aplica_deduccion_salario_snapshot',
        'tipo_falta_nombre_snapshot',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ocurrencia' => 'date',
            'fecha_deduccion_nomina' => 'date',
            'deduccion_salario_base' => 'decimal:2',
            'deduccion_bono_puntualidad' => 'decimal:2',
            'deduccion_bono_productividad' => 'decimal:2',
            'total_deduccion' => 'integer',
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

    public function tipoFalta(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoFalta::class, 'catalogo_tipo_falta_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
