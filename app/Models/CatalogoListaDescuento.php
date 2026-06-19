<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CatalogoListaDescuento extends Model
{
    protected $table = 'catalogo_listas_descuento';

    protected $fillable = [
        'nombre',
        'monto_requerido',
        'porcentaje_descuento',
        'monto_minimo',
        'monto_maximo',
        'activo'
    ];

    protected $casts = [
        'monto_requerido' => 'decimal:2',
        'porcentaje_descuento' => 'decimal:2',
        'activo' => 'boolean',
    ];

    protected $appends = [
        'porcentaje_escalonamiento_pct',
    ];

    public function getPorcentajeEscalonamientoPctAttribute(): float
    {
        if (!$this->relationLoaded('porcentajeEscalonamiento')) {
            $this->load('porcentajeEscalonamiento');
        }

        $pct = $this->porcentajeEscalonamiento;

        if (!$pct || !$pct->activo) {
            return 0.0;
        }

        return (float) $pct->porcentaje_descuento;
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'lista_actual_id');
    }

    /** Porcentaje simple aplicado a cotización en ejercicio de escalonamiento (ej. PLATA 2%). */
    public function porcentajeEscalonamiento(): HasOne
    {
        return $this->hasOne(CatalogoPorcentajeEscalonamientoLista::class, 'catalogo_lista_descuento_id');
    }

    /** Porcentaje para generación de listas de resurtido / export Excel (ej. PLATA 14.14%). */
    public function porcentajeListado(): HasOne
    {
        return $this->hasOne(CatalogoPorcentajeListadoLista::class, 'catalogo_lista_descuento_id');
    }
}
