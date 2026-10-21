<?php

namespace App\Services\Productos\Imagenes;

use App\Models\User;

class AutorizacionImagenProducto
{
    public function puedeConsultar(?User $user): bool
    {
        return $user instanceof User && (
            $user->can('gestion_interna.productos.ver')
            || $user->can('catalogos.gestionar')
            || $user->can('almacenes.productos.ver')
        );
    }

    public function puedeGestionar(?User $user): bool
    {
        return $user instanceof User && (
            $user->can('gestion_interna.productos.gestionar')
            || $user->can('catalogos.gestionar')
        );
    }
}
