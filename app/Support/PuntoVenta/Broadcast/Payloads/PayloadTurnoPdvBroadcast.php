<?php

namespace App\Support\PuntoVenta\Broadcast\Payloads;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;

final class PayloadTurnoPdvBroadcast
{
    public static function eventIdDesdeEvento(TurnoPdvEvento $evento): string
    {
        return 'turnos:'.$evento->tipo_evento.':'.$evento->id;
    }

    /**
     * @return array<string, mixed>
     */
    public static function sucursal(TurnoPdv $turno, ?TurnoPdvAtencion $atencion = null): array
    {
        return array_merge(
            self::resumenTurno($turno),
            [
                'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
                'atencion' => $atencion instanceof TurnoPdvAtencion
                    ? self::atencionSucursal($atencion)
                    : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function usuario(TurnoPdv $turno, TurnoPdvAtencion $atencion): array
    {
        return array_merge(
            self::resumenTurno($turno),
            [
                'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
                'atencion' => self::atencionUsuario($atencion),
            ],
        );
    }

    /**
     * Payload de sala: folio, nombre de llamado y primer nombre de quien atiende.
     * Excepción de privacidad CONTRATO_PANTALLAS_PDV §2–§4.1; sin VIP ni datos sensibles.
     *
     * @return array<string, mixed>
     */
    public static function publico(TurnoPdv $turno, ?TurnoPdvAtencion $atencion = null): array
    {
        $persona = $atencion?->relationLoaded('user') ? $atencion->user : null;

        return [
            'turno_id' => $turno->id,
            'folio' => $turno->folio,
            'servicio' => $turno->servicio,
            'estado' => $turno->estado,
            'prioridad_diamante' => (bool) $turno->prioridad_diamante,
            'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
            'atencion_primer_nombre' => $persona instanceof User
                ? self::primerNombre($persona->name)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resumenTurno(TurnoPdv $turno): array
    {
        return [
            'turno_id' => $turno->id,
            'folio' => $turno->folio,
            'estado' => $turno->estado,
            'servicio' => $turno->servicio,
            'prioridad_diamante' => (bool) $turno->prioridad_diamante,
            'prioridad_vip' => (bool) $turno->prioridad_vip,
            'prioridad_adulto_mayor' => (bool) $turno->prioridad_adulto_mayor,
            'prioridad_discapacidad' => (bool) $turno->prioridad_discapacidad,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function atencionSucursal(TurnoPdvAtencion $atencion): array
    {
        $persona = $atencion->relationLoaded('user') ? $atencion->user : null;

        return [
            'id' => $atencion->id,
            'user_id' => $atencion->user_id,
            'primer_nombre' => $persona instanceof User
                ? self::primerNombre($persona->name)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function atencionUsuario(TurnoPdvAtencion $atencion): array
    {
        return [
            'id' => $atencion->id,
            'user_id' => $atencion->user_id,
            'inicio_at' => $atencion->inicio_at?->toIso8601String(),
            'atencion_inicio_at' => $atencion->atencion_inicio_at?->toIso8601String(),
            'es_transferencia' => $atencion->es_transferencia,
        ];
    }

    private static function primerNombre(?string $nombreCompleto): ?string
    {
        $partes = preg_split('/\s+/', trim((string) $nombreCompleto)) ?: [];

        return $partes[0] ?? null;
    }
}
