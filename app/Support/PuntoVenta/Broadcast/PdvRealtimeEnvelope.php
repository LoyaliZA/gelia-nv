<?php

namespace App\Support\PuntoVenta\Broadcast;

use Carbon\CarbonInterface;

final class PdvRealtimeEnvelope
{
    public const PAYLOAD_VERSION = 1;

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function crear(
        string $eventId,
        string $tipo,
        string $dominio,
        string $audiencia,
        int $sucursalId,
        int $version,
        array $datos,
        ?CarbonInterface $ocurridoAt = null,
    ): array {
        return [
            'event_id' => $eventId,
            'tipo' => $tipo,
            'dominio' => $dominio,
            'audiencia' => $audiencia,
            'sucursal_id' => $sucursalId,
            'version' => $version,
            'payload_version' => self::PAYLOAD_VERSION,
            'ocurrido_at' => ($ocurridoAt ?? now())->toIso8601String(),
            'datos' => $datos,
        ];
    }
}
