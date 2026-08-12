<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoNotaOlfativa extends Model
{
    protected $table = 'producto_notas_olfativas';

    protected $fillable = [
        'producto_id',
        'nota_olfativa_id',
        'fase_olfativa_id',
        'orden',
        'prominencia',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaOlfativa::class, 'nota_olfativa_id');
    }

    public function fase(): BelongsTo
    {
        return $this->belongsTo(FaseOlfativa::class, 'fase_olfativa_id');
    }
}
