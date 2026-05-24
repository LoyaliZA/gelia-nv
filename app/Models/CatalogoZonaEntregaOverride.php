<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoZonaEntregaOverride extends Model
{
    protected $table = 'catalogo_zona_entrega_overrides';

    protected $fillable = [
        'nombre',
        'coordenadas_poligono',
        'zona_referencia_id',
        'activo',
        'prioridad',
    ];

    protected function casts(): array
    {
        return [
            'coordenadas_poligono' => 'array',
            'activo' => 'boolean',
            'prioridad' => 'integer',
        ];
    }

    public function zonaReferencia(): BelongsTo
    {
        return $this->belongsTo(CatalogoZonaEntrega::class, 'zona_referencia_id');
    }
}
