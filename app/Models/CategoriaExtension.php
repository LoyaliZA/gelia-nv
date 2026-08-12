<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaExtension extends Model
{
    protected $table = 'categoria_extensiones';

    protected $fillable = [
        'categoria_id',
        'extension_id',
        'habilitada',
        'heredable',
        'configuracion_json',
    ];

    protected function casts(): array
    {
        return [
            'habilitada' => 'boolean',
            'heredable' => 'boolean',
            'configuracion_json' => 'array',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CatalogoCategoriaProducto::class, 'categoria_id');
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(ExtensionProducto::class, 'extension_id');
    }
}
