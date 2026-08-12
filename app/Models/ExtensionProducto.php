<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtensionProducto extends Model
{
    protected $table = 'extensiones_producto';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'version',
        'habilitada',
        'configuracion_json',
    ];

    protected function casts(): array
    {
        return [
            'habilitada' => 'boolean',
            'configuracion_json' => 'array',
        ];
    }

    public function categoriaExtensiones(): HasMany
    {
        return $this->hasMany(CategoriaExtension::class, 'extension_id');
    }
}
