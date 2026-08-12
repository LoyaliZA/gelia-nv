<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaOlfativa extends Model
{
    protected $table = 'notas_olfativas';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function productoNotas(): HasMany
    {
        return $this->hasMany(ProductoNotaOlfativa::class, 'nota_olfativa_id');
    }
}
