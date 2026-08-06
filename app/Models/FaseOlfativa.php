<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaseOlfativa extends Model
{
    protected $table = 'fases_olfativas';

    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
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
        return $this->hasMany(ProductoNotaOlfativa::class, 'fase_olfativa_id');
    }
}
