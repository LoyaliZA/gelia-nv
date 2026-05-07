<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    // --- SECCIÓN: CONFIGURACIÓN ---
    protected $fillable = [
        'nombre',
        'departamento_id'
    ];

    // --- SECCIÓN: RELACIONES ---

    /**
     * Relación: Un área pertenece a un departamento específico.
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * Relación: Un área puede tener muchos usuarios asignados.
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}