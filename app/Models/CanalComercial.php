<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanalComercial extends Model
{
    protected $table = 'canales_comerciales';

    protected $fillable = [
        'nombre',
        'codigo',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function contenidos(): HasMany
    {
        return $this->hasMany(ProductoContenido::class, 'canal_id');
    }
}
