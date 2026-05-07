<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabuladorComision extends Model
{
    protected $table = 'tabulador_comisiones';

    protected $fillable = [
        'catalogo_proceso_id',
        'monto_comision',
        'monto_vendedora', // AGREGADO: Para la vendedora actual
        'monto_original',  // AGREGADO: Para la vendedora que prospectó (Heredados)
        'activo'
    ];

    protected $casts = [
        'monto_comision' => 'decimal:2',
        'monto_vendedora' => 'decimal:2',
        'monto_original' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(CatalogoProceso::class, 'catalogo_proceso_id');
    }
}