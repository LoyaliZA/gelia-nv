<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory; // <-- IMPORTACIÓN FALTANTE

class Departamento extends Model
{
    use HasFactory;

    // --- SECCIÓN: CONFIGURACIÓN ---
    protected $fillable = [
        'nombre',
        'codigo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // --- SECCIÓN: RELACIONES ---

    /**
     * Relación: Un departamento tiene muchas áreas.
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function usuarios()
    {
        return $this->belongsToMany(User::class);
    }
}