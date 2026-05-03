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
        'activo'
    ];

    protected $casts = [
        'monto_comision' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(CatalogoProceso::class, 'catalogo_proceso_id');
    }
}