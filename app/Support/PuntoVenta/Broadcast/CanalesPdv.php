<?php

namespace App\Support\PuntoVenta\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

final class CanalesPdv
{
    public static function sucursal(int $sucursalId): PrivateChannel
    {
        return new PrivateChannel('pdv.sucursal.'.$sucursalId);
    }

    public static function usuario(int $userId): PrivateChannel
    {
        return new PrivateChannel('pdv.usuario.'.$userId);
    }

    public static function turnosPublico(int $sucursalId): Channel
    {
        return new Channel('pdv.turnos.publico.'.$sucursalId);
    }
}
