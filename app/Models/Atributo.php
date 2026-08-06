<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Atributo extends Model
{
    protected $table = 'atributos';

    protected $fillable = [
        'nombre',
        'slug',
        'tipo_dato',
        'permite_multiples',
        'dimension_unidad',
        'filtrable',
        'buscable',
        'visible_en_ficha',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'permite_multiples' => 'boolean',
            'filtrable' => 'boolean',
            'buscable' => 'boolean',
            'visible_en_ficha' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(AtributoOpcion::class, 'atributo_id')->orderBy('orden');
    }

    public function categoriaAtributos(): HasMany
    {
        return $this->hasMany(CategoriaAtributo::class, 'atributo_id');
    }
}
