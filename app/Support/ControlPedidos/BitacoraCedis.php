<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use SplObjectStorage;

final class BitacoraCedis
{
    public const PERMISO = 'control_pedidos.cedis.bitacora';

    public static function puedeVer(?User $usuario): bool
    {
        return $usuario && ($usuario->hasRole('Super Admin')
            || ! $usuario->can('control_pedidos.cedis')
            || $usuario->can(self::PERMISO));
    }

    /** La relación sigue disponible para reglas internas; se excluye de la respuesta. */
    public static function presentar(PedidoBma $pedido, ?User $usuario): PedidoBma
    {
        $permitido = self::puedeVer($usuario);
        $pedido->setAttribute('puede_ver_bitacora', $permitido);
        if (! $permitido) {
            self::ocultarHistorial($pedido, new SplObjectStorage);
        }

        return $pedido;
    }

    private static function ocultarHistorial(Model $modelo, SplObjectStorage $visitados): void
    {
        if ($visitados->contains($modelo)) {
            return;
        }
        $visitados->attach($modelo);
        $modelo->makeHidden('historial');
        foreach ($modelo->getRelations() as $nombre => $relacion) {
            if ($nombre === 'historial') {
                continue;
            }
            if ($relacion instanceof Model) {
                self::ocultarHistorial($relacion, $visitados);
            } elseif ($relacion instanceof Collection) {
                foreach ($relacion as $item) {
                    if ($item instanceof Model) {
                        self::ocultarHistorial($item, $visitados);
                    }
                }
            }
        }
    }
}
