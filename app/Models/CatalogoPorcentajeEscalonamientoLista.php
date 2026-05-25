<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoPorcentajeEscalonamientoLista extends Model
{
    protected $table = 'catalogo_porcentajes_escalonamiento_lista';

    protected $fillable = [
        'catalogo_lista_descuento_id',
        'porcentaje_descuento',
        'activo',
    ];

    protected $casts = [
        'porcentaje_descuento' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function listaDescuento(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'catalogo_lista_descuento_id');
    }
}
