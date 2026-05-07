<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    // --- SECCIÓN: CAMPOS PERMITIDOS ---
    protected $fillable = [
        'name',
        'username',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'password',
        'telefono',
        'edad',
        'foto_perfil',
        'catalogo_sexo_id',
        'area_id' // Agregado para permitir la asignación organizacional
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // --- SECCIÓN: CONVERSIÓN DE DATOS ---
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // --- SECCIÓN: RELACIONES ---

    /**
     * Relación con el sexo del usuario.
     */
    public function sexo(): BelongsTo
    {
        return $this->belongsTo(CatalogoSexo::class, 'catalogo_sexo_id');
    }

    /**
     * Relación: Un usuario pertenece a un Área.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Acceso directo al Departamento (Atributo dinámico).
     * Permite hacer $user->departamento
     */
    public function getDepartamentoAttribute()
    {
        return $this->area ? $this->area->departamento : null;
    }
}