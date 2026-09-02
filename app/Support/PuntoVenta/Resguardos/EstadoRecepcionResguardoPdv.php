<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use Illuminate\Support\Collection;

final class EstadoRecepcionResguardoPdv
{
    /**
     * @return Collection<int, ResguardoPdvBulto>
     */
    public static function bultosRecibidos(ResguardoPdv $resguardo): Collection
    {
        $bultos = $resguardo->relationLoaded('bultos')
            ? $resguardo->bultos
            : $resguardo->bultos()->get();

        return $bultos
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO)
            ->values();
    }

    public static function cantidadRecibida(ResguardoPdv $resguardo): int
    {
        return self::bultosRecibidos($resguardo)->count();
    }

    public static function cantidadPendiente(ResguardoPdv $resguardo): int
    {
        return max(0, (int) $resguardo->cantidad_bultos_esperada - self::cantidadRecibida($resguardo));
    }

    public static function recepcionCompleta(ResguardoPdv $resguardo): bool
    {
        return self::cantidadPendiente($resguardo) === 0
            && self::cantidadRecibida($resguardo) > 0;
    }

    public static function admiteRecepcion(ResguardoPdv $resguardo): bool
    {
        if (! in_array($resguardo->estado, [
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
        ], true)) {
            return false;
        }

        return self::cantidadPendiente($resguardo) > 0;
    }
}
