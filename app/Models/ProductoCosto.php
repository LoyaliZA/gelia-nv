<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoCosto extends Model
{
    protected $table = 'producto_costos';

    protected $fillable = [
        'producto_id',
        'costo',
        'costo_reposicion',
    ];

    protected function casts(): array
    {
        return [
            'costo' => 'decimal:2',
            'costo_reposicion' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
