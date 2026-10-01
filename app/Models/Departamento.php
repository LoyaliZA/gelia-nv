<?php

namespace App\Models;

use App\Models\Concerns\FiltraFilasDemo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- IMPORTACIÓN FALTANTE

class Departamento extends Model
{
    use FiltraFilasDemo, HasFactory;

    // --- SECCIÓN: CONFIGURACIÓN ---
    protected $fillable = [
        'nombre',
        'codigo',
        'logo_key_claro',
        'logo_key_oscuro',
        'activo',
        'visible_origen_resguardo_pdv',
        'es_demo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'visible_origen_resguardo_pdv' => 'boolean',
        'es_demo' => 'boolean',
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
