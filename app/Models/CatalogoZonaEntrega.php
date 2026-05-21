<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoZonaEntrega extends Model
{
    /**
     * Nombre de la tabla asociada al modelo.
     */
    protected $table = 'catalogo_zonas_entrega';

    /**
     * Atributos asignables de forma masiva.
     */
    protected $fillable = [
        'nombre',
        'coordenadas_poligono',
        'color_hex',
        'costo_base',
        'activo',
    ];

    /**
     * Conversión de tipos de atributos nativos.
     */
    protected function casts(): array
    {
        return [
            'coordenadas_poligono' => 'array',
            'activo' => 'boolean',
            'costo_base' => 'decimal:2',
        ];
    }

    /**
     * Relación uno a muchos hacia los horarios asignados a la zona.
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(CatalogoHorarioEntrega::class, 'zona_id');
    }
}