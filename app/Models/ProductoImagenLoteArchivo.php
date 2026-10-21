<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoImagenLoteArchivo extends Model
{
    protected $table = 'producto_imagen_lote_archivos';

    protected $fillable = [
        'lote_id',
        'indice',
        'nombre_original',
        'folio_detectado',
        'producto_id',
        'imagen_id',
        'estado',
        'clasificacion',
        'motivo',
        'apoyo_sku',
        'candidatos',
        'hash_contenido',
        'clave_temporal',
        'reclamado_en',
    ];

    protected function casts(): array
    {
        return [
            'candidatos' => 'array',
            'reclamado_en' => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(ProductoImagenLote::class, 'lote_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(ProductoImagen::class, 'imagen_id');
    }
}
