<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\CatalogoSexo;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    // Sintaxis que Laravel sí entiende para empaquetar los datos a React
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
        'catalogo_sexo_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sexo()
    {
        return $this->belongsTo(CatalogoSexo::class, 'catalogo_sexo_id');
    }
}