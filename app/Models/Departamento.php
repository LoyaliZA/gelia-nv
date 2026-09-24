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
        'logo_key_claro',
        'logo_key_oscuro',
        'activo',
        'visible_origen_resguardo_pdv',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'visible_origen_resguardo_pdv' => 'boolean',
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