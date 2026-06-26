<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTurno extends Model
{
    protected $table = 'catalogo_turnos';

    protected $fillable = [
        'nombre',
        'matriz_horario',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'matriz_horario' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(RhColaborador::class, 'catalogo_turno_id');
    }
}
