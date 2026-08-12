<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoRelacion extends Model
{
    protected $table = 'producto_relaciones';

    protected $fillable = [
        'producto_id',
        'producto_relacionado_id',
        'tipo',
        'orden',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function relacionado(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_relacionado_id');
    }
}
