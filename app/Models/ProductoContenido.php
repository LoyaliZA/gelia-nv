<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoContenido extends Model
{
    protected $table = 'producto_contenidos';

    protected $fillable = [
        'producto_id',
        'canal_id',
        'idioma',
        'titulo_comercial',
        'descripcion_corta',
        'descripcion_larga',
        'pitch_venta',
        'seo_titulo',
        'seo_descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function canal(): BelongsTo
    {
        return $this->belongsTo(CanalComercial::class, 'canal_id');
    }
}
