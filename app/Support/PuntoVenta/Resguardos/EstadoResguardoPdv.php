<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use Illuminate\Support\Collection;

final class EstadoResguardoPdv
{
    /**
     * @return Collection<int, ResguardoPdvBulto>
     */
    public static function bultos(ResguardoPdv $resguardo): Collection
    {
        return $resguardo->relationLoaded('bultos')
            ? $resguardo->bultos
            : $resguardo->bultos()->get();
    }

    public static function cantidadEsperada(ResguardoPdv $resguardo): int
    {
        return max(0, (int) $resguardo->cantidad_bultos_esperada);
    }

    public static function cantidadRecibidaGerente(ResguardoPdv $resguardo): int
    {
        if (isset($resguardo->bultos_recibidos_gerente_count)) {
            return (int) $resguardo->bultos_recibidos_gerente_count;
        }

        return self::bultos($resguardo)
            ->filter(fn (ResguardoPdvBulto $bulto) => in_array($bulto->estado, [
                ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE,
                ResguardoPdvBulto::ESTADO_EN_CUSTODIA,
                ResguardoPdvBulto::ESTADO_ENTREGADO,
                ResguardoPdvBulto::ESTADO_RECIBIDO,
            ], true))
            ->count();
    }

    public static function cantidadPendienteGerente(ResguardoPdv $resguardo): int
    {
        return max(0, self::cantidadEsperada($resguardo) - self::cantidadRecibidaGerente($resguardo));
    }

    public static function recepcionGerenteCompleta(ResguardoPdv $resguardo): bool
    {
        return self::cantidadPendienteGerente($resguardo) === 0
            && self::cantidadRecibidaGerente($resguardo) > 0;
    }

    public static function cantidadEnCustodia(ResguardoPdv $resguardo): int
    {
        if (isset($resguardo->bultos_en_custodia_count)) {
            return (int) $resguardo->bultos_en_custodia_count;
        }

        return self::bultos($resguardo)
            ->filter(fn (ResguardoPdvBulto $bulto) => ResguardoPdvBulto::estaEnCustodiaOperativa($bulto->estado))
            ->count();
    }

    public static function cantidadPendienteCustodia(ResguardoPdv $resguardo): int
    {
        return self::bultos($resguardo)
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE)
            ->count();
    }

    public static function custodiaCompleta(ResguardoPdv $resguardo): bool
    {
        return self::recepcionGerenteCompleta($resguardo)
            && self::cantidadPendienteCustodia($resguardo) === 0
            && self::cantidadEnCustodia($resguardo) > 0;
    }

    public static function admiteRecepcionGerente(ResguardoPdv $resguardo): bool
    {
        if ($resguardo->estado !== ResguardoPdv::ESTADO_PENDIENTE_RECEPCION) {
            return false;
        }

        return self::cantidadPendienteGerente($resguardo) > 0;
    }

    public static function admitePasarARecepcion(ResguardoPdv $resguardo): bool
    {
        return $resguardo->estado === ResguardoPdv::ESTADO_RECIBIDO
            && self::recepcionGerenteCompleta($resguardo);
    }

    public static function admiteConfirmacionCustodia(ResguardoPdv $resguardo): bool
    {
        if ($resguardo->estado !== ResguardoPdv::ESTADO_EN_RECEPCION) {
            return false;
        }

        return self::cantidadPendienteCustodia($resguardo) > 0;
    }

    public static function resolverEstadoOperativo(ResguardoPdv $resguardo): string
    {
        if (in_array($resguardo->estado, [
            ResguardoPdv::ESTADO_ENTREGADO,
            ResguardoPdv::ESTADO_DEVUELTO,
            ResguardoPdv::ESTADO_RECIBIDO,
            ResguardoPdv::ESTADO_EN_RECEPCION,
        ], true)) {
            if ($resguardo->estado === ResguardoPdv::ESTADO_EN_RECEPCION
                && self::custodiaCompleta($resguardo)) {
                return ResguardoPdv::ESTADO_EN_CUSTODIA;
            }

            return $resguardo->estado;
        }

        if (self::cantidadPendienteGerente($resguardo) > 0) {
            return ResguardoPdv::ESTADO_PENDIENTE_RECEPCION;
        }

        if (self::cantidadPendienteCustodia($resguardo) > 0) {
            return ResguardoPdv::ESTADO_EN_RECEPCION;
        }

        if (self::cantidadEnCustodia($resguardo) > 0) {
            return ResguardoPdv::ESTADO_EN_CUSTODIA;
        }

        return $resguardo->estado;
    }

    public static function motivoNoRecepcionGerente(ResguardoPdv $resguardo): ?string
    {
        if (self::admiteRecepcionGerente($resguardo)) {
            return null;
        }

        if (self::recepcionGerenteCompleta($resguardo)) {
            return 'recepcion_gerente_completa';
        }

        return 'estado_invalido';
    }

    public static function motivoNoPasarARecepcion(ResguardoPdv $resguardo): ?string
    {
        if (self::admitePasarARecepcion($resguardo)) {
            return null;
        }

        if ($resguardo->estado === ResguardoPdv::ESTADO_EN_RECEPCION) {
            return 'ya_en_recepcion';
        }

        if (! self::recepcionGerenteCompleta($resguardo)) {
            return 'recepcion_gerente_incompleta';
        }

        return 'estado_invalido';
    }

    public static function motivoNoConfirmacionCustodia(ResguardoPdv $resguardo): ?string
    {
        if (self::admiteConfirmacionCustodia($resguardo)) {
            return null;
        }

        if (self::custodiaCompleta($resguardo)) {
            return 'custodia_completa';
        }

        if ($resguardo->estado === ResguardoPdv::ESTADO_RECIBIDO) {
            return 'pendiente_pasar_a_recepcion';
        }

        if (self::cantidadPendienteGerente($resguardo) > 0) {
            return 'recepcion_gerente_incompleta';
        }

        return 'estado_invalido';
    }
}
