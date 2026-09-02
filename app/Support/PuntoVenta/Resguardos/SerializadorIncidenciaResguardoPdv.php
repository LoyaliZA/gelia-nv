<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;

final class SerializadorIncidenciaResguardoPdv
{
    /**
     * @return array<string, mixed>
     */
    public static function incidencia(ResguardoPdvIncidencia $incidencia): array
    {
        return [
            'id' => $incidencia->id,
            'tipo' => $incidencia->tipo,
            'tipo_etiqueta' => EtiquetasResguardoPdv::tiposIncidencia()[$incidencia->tipo] ?? $incidencia->tipo,
            'estado' => $incidencia->estado,
            'estado_etiqueta' => EtiquetasResguardoPdv::estadosIncidencia()[$incidencia->estado] ?? $incidencia->estado,
            'descripcion' => $incidencia->descripcion,
            'bulto_id' => $incidencia->bulto_id,
            'reportado_por_id' => $incidencia->reportado_por_id,
            'reportado_por_referencia' => self::referenciaActor(
                $incidencia->relationLoaded('reportadoPor') ? $incidencia->reportadoPor : null
            ),
            'reportado_at' => $incidencia->reportado_at?->toIso8601String(),
            'autorizado_por_id' => $incidencia->autorizado_por_id,
            'autorizado_por_referencia' => self::referenciaActor(
                $incidencia->relationLoaded('autorizadoPor') ? $incidencia->autorizadoPor : null
            ),
            'autorizado_at' => $incidencia->autorizado_at?->toIso8601String(),
            'motivo_resolucion' => $incidencia->motivo_autorizacion,
            'version' => (int) $incidencia->version,
            'evidencias' => $incidencia->relationLoaded('evidencias')
                ? $incidencia->evidencias->map(fn ($evidencia) => [
                    'id' => $evidencia->id,
                    'tipo' => $evidencia->tipo,
                    'nombre_original' => $evidencia->nombre_original,
                    'capturado_at' => $evidencia->capturado_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resguardo(ResguardoPdv $resguardo): array
    {
        return [
            'id' => $resguardo->id,
            'estado' => $resguardo->estado,
            'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
            'version' => (int) $resguardo->version,
            'cantidad_bultos_esperada' => (int) $resguardo->cantidad_bultos_esperada,
            'cantidad_bultos_recibida' => EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo),
            'cantidad_bultos_pendiente' => EstadoRecepcionResguardoPdv::cantidadPendiente($resguardo),
            'recepcion_completa' => EstadoRecepcionResguardoPdv::recepcionCompleta($resguardo),
        ];
    }

    private static function referenciaActor(?User $actor): ?string
    {
        if (! $actor instanceof User) {
            return null;
        }

        if (filled($actor->username)) {
            return '@'.$actor->username;
        }

        return 'Colaborador';
    }
}
