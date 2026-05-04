<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\CatalogoSexo;
use Spatie\Permission\Traits\HasRoles; // Importación única de Spatie

// Actualiza el atributo #[Fillable] en la parte superior de tu clase
#[Fillable([
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
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    // Declaración estricta de los Traits. Aquí inyectamos HasRoles
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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


    // Se han eliminado las funciones rol() y hasRole() manuales 
    // para delegar el control absoluto al paquete de Spatie.
}
