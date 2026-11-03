<?php

namespace App\Support\Api\V1;

use App\Models\User;

final class AutorizacionMovil
{
    /**
     * @param  list<string>  $permisos
     */
    public static function alguno(User $usuario, array $permisos): void
    {
        foreach ($permisos as $permiso) {
            if ($usuario->can($permiso)) {
                return;
            }
        }

        abort(403, 'No tiene autorización para esta operación.');
    }
}
