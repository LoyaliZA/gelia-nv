<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubeImageImportItem extends Model
{
    protected $table = 'tiendanube_image_import_items';

    protected $fillable = [
        'import_id',
        'filename',
        'relative_path',
        'sku',
        'position',
        'producto_id',
        'estado',
        'motivo',
        'mensaje',
        'imagen_tn_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'producto_id' => 'integer',
            'imagen_tn_id' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(TiendanubeImageImport::class, 'import_id');
    }
}
