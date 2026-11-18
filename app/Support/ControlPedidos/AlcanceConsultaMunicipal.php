<?php

namespace App\Support\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AlcanceConsultaMunicipal
{
    public static function aplicarTienda(Builder $query, User $usuario): void
    {
        if (! VisibilidadPedidoBma::esAdmin($usuario)) {
            $query->whereIn('solicitud_preparacion_json->consulta_municipal->almacen_id',
                Almacen::query()->select('id')->whereIn('sucursal_id', $usuario->idsSucursalesOperables()));
        }
    }

    public static function puedeResponder(User $usuario, PedidoBma $pedido): bool
    {
        if (! ConsultaMunicipal::es($pedido) || empty(ConsultaMunicipal::seguimiento($pedido)['enviada_at'])) {
            return false;
        }
        $consulta = ConsultaMunicipal::seguimiento($pedido);
        if (($consulta['area'] ?? null) === 'cedis') {
            return $usuario->can('control_pedidos.cedis') && VisibilidadPedidoBma::puedeVerEnCedis($usuario, $pedido);
        }
        if (($consulta['area'] ?? null) !== 'tienda' || ! $usuario->can('control_pedidos.municipal.responder_consulta')) {
            return false;
        }
        $almacen = Almacen::find($consulta['almacen_id'] ?? null);

        return $almacen && AlmacenPdv::esAlmacen($almacen)
            && (VisibilidadPedidoBma::esAdmin($usuario) || $usuario->idsSucursalesOperables()->contains((int) $almacen->sucursal_id));
    }
}
