<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaAtributo extends Model
{
    protected $table = 'categoria_atributos';

    protected $fillable = [
        'categoria_id',
        'atributo_id',
        'requerido',
        'heredable',
        'filtrable_override',
        'visible_override',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'requerido' => 'boolean',
            'heredable' => 'boolean',
            'filtrable_override' => 'boolean',
            'visible_override' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CatalogoCategoriaProducto::class, 'categoria_id');
    }

    public function atributo(): BelongsTo
    {
        return $this->belongsTo(Atributo::class, 'atributo_id');
    }
}
