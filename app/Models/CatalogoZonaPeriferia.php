<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoZonaPeriferia extends Model
{
    protected $table = 'catalogo_zonas_periferia';

    protected $fillable = [
        'nombre',
        'coordenadas_poligono',
        'zona_referencia_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'coordenadas_poligono' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function zonaReferencia(): BelongsTo
    {
        return $this->belongsTo(CatalogoZonaEntrega::class, 'zona_referencia_id');
    }
}
