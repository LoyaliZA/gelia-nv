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
    public static function bultosRecibidosGerente(ResguardoPdv $resguardo): Collection
    {
        return EstadoResguardoPdv::bultos($resguardo)
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE)
            ->values();
    }

    /**
     * @return Collection<int, ResguardoPdvBulto>
     */
    public static function bultosRecibidos(ResguardoPdv $resguardo): Collection
    {
        return self::bultosRecibidosGerente($resguardo);
    }

    public static function cantidadRecibida(ResguardoPdv $resguardo): int
    {
        if (isset($resguardo->bultos_recibidos_gerente_count)) {
            return (int) $resguardo->bultos_recibidos_gerente_count;
        }

        if (isset($resguardo->bultos_recibidos_count)) {
            return (int) $resguardo->bultos_recibidos_count;
        }

        return EstadoResguardoPdv::cantidadRecibidaGerente($resguardo);
    }

    public static function cantidadPendiente(ResguardoPdv $resguardo): int
    {
        return EstadoResguardoPdv::cantidadPendienteGerente($resguardo);
    }

    public static function motivoNoRecepcion(ResguardoPdv $resguardo): ?string
    {
        return EstadoResguardoPdv::motivoNoRecepcionGerente($resguardo);
    }

    public static function recepcionCompleta(ResguardoPdv $resguardo): bool
    {
        return EstadoResguardoPdv::recepcionGerenteCompleta($resguardo);
    }

    public static function admiteRecepcion(ResguardoPdv $resguardo): bool
    {
        return EstadoResguardoPdv::admiteRecepcionGerente($resguardo);
    }
}
