<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Services\PuntoVenta\PuntoVentaModulo;

final class IncidenciaResguardoPdv
{
    /**
     * @return list<string>
     */
    public static function tiposRegistrables(): array
    {
        return [
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
            ResguardoPdvIncidencia::TIPO_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE,
        ];
    }

    public static function permisoRegistro(string $tipo): ?string
    {
        return match ($tipo) {
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO,
            ResguardoPdvIncidencia::TIPO_DANO => PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE => PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE,
            default => null,
        };
    }

    public static function tipoEventoRegistro(string $tipo): ?string
    {
        return match ($tipo) {
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => ResguardoPdvEvento::TIPO_INCIDENCIA_FOLIO_NO_ENCONTRADO,
            ResguardoPdvIncidencia::TIPO_DANO => ResguardoPdvEvento::TIPO_INCIDENCIA_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE => ResguardoPdvEvento::TIPO_INCIDENCIA_FALTANTE,
            default => null,
        };
    }

    public static function exigeEvidencia(string $tipo): bool
    {
        return in_array($tipo, [
            ResguardoPdvIncidencia::TIPO_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE,
        ], true);
    }

    public static function exigeBultoAlRegistrar(string $tipo): bool
    {
        return $tipo === ResguardoPdvIncidencia::TIPO_DANO;
    }

    public static function admiteRegistro(ResguardoPdv $resguardo): bool
    {
        return in_array($resguardo->estado, [
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
        ], true);
    }

    public static function admiteResolucion(ResguardoPdvIncidencia $incidencia): bool
    {
        return $incidencia->estado === ResguardoPdvIncidencia::ESTADO_ABIERTA;
    }

    public static function permisoResolucion(ResguardoPdvIncidencia $incidencia): ?string
    {
        return match ($incidencia->tipo) {
            ResguardoPdvIncidencia::TIPO_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE => PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA,
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO,
            default => null,
        };
    }

    public static function estadoResolucion(ResguardoPdvIncidencia $incidencia): ?string
    {
        return match ($incidencia->tipo) {
            ResguardoPdvIncidencia::TIPO_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE => ResguardoPdvIncidencia::ESTADO_AUTORIZADA,
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => ResguardoPdvIncidencia::ESTADO_CERRADA,
            default => null,
        };
    }

    public static function tipoEventoResolucion(ResguardoPdvIncidencia $incidencia): ?string
    {
        return match ($incidencia->tipo) {
            ResguardoPdvIncidencia::TIPO_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE => ResguardoPdvEvento::TIPO_INCIDENCIA_ENTREGA_AUTORIZADA,
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => ResguardoPdvEvento::TIPO_INCIDENCIA_CERRADA,
            default => null,
        };
    }
}
