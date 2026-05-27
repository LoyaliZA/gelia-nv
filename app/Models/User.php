<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

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
        'fecha_nacimiento',
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
            'fecha_nacimiento' => 'date',
        ];
    }

    // --- SECCIÓN: RELACIONES ---

    public function sexo(): BelongsTo
    {
        return $this->belongsTo(CatalogoSexo::class, 'catalogo_sexo_id');
    }

    // --- SECCIÓN: RELACIONES MATRICIALES ---

    public function departamentos()
    {
        return $this->belongsToMany(Departamento::class);
    }

    public function areas()
    {
        return $this->belongsToMany(Area::class);
    }

    // Quiénes son los gerentes de este usuario
    public function gerentes()
    {
        return $this->belongsToMany(User::class, 'gerente_colaborador', 'colaborador_id', 'gerente_id');
    }

    // Quiénes son los colaboradores a cargo de este gerente
    public function colaboradores()
    {
        return $this->belongsToMany(User::class, 'gerente_colaborador', 'gerente_id', 'colaborador_id');
    }
    // Listas creadas por el usuario
    public function customLists()
    {
        return $this->hasMany(CustomList::class);
    }

    // Listas que le han compartido
    public function sharedCustomLists()
    {
        return $this->belongsToMany(CustomList::class, 'custom_list_user', 'user_id', 'custom_list_id');
    }

    public function permisoProcedencia(): HasMany
    {
        return $this->hasMany(UsuarioPermisoProcedencia::class);
    }

    /**
     * Los roles ya no otorgan acceso; solo identidad organizacional.
     */
    public function getPermissionsViaRoles(): Collection
    {
        return collect();
    }
}
