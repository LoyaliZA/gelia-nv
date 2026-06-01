<?php

namespace App\Services\Rh;

use App\Models\User;

class SincronizarDatosUsuarioService
{
    /**
     * @return array{nombre: string, apellido_paterno: ?string, apellido_materno: ?string}
     */
    public function ejecutar(User $usuario): array
    {
        return [
            'nombre' => $usuario->name,
            'apellido_paterno' => $usuario->apellido_paterno,
            'apellido_materno' => $usuario->apellido_materno,
        ];
    }
}
