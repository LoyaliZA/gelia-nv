<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoFalta extends Model
{
    protected $table = 'catalogo_tipos_faltas';

    protected $fillable = [
        'nombre',
        'factor_penalizacion_puntualidad',
        'factor_penalizacion_productividad',
        'aplica_deduccion_salario_base',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'factor_penalizacion_puntualidad' => 'decimal:2',
            'factor_penalizacion_productividad' => 'decimal:2',
            'aplica_deduccion_salario_base' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(RhIncidencia::class, 'catalogo_tipo_falta_id');
    }
}
