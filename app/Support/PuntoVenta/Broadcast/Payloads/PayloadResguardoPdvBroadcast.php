<?php

namespace App\Support\PuntoVenta\Broadcast\Payloads;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;

final class PayloadResguardoPdvBroadcast
{
    /**
     * @return array<string, mixed>
     */
    public static function desdeResguardo(ResguardoPdv $resguardo, ?string $tipoEvento = null): array
    {
        return [
            'resguardo_id' => $resguardo->id,
            'folio' => $resguardo->snapshot_folio,
            'estado' => $resguardo->estado,
            'tipo_evento' => $tipoEvento,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function desdeEvento(ResguardoPdv $resguardo, ResguardoPdvEvento $evento): array
    {
        return array_merge(
            self::desdeResguardo($resguardo, $evento->tipo_evento),
            [
                'estado_anterior' => $evento->estado_anterior,
                'estado_nuevo' => $evento->estado_nuevo,
            ],
        );
    }

    public static function eventIdDesdeEvento(ResguardoPdvEvento $evento): string
    {
        return 'resguardos:'.$evento->tipo_evento.':'.$evento->id;
    }

    public static function eventIdDesdeHandoff(int $resguardoId, int $pedidoBmaId): string
    {
        return 'resguardos:resguardo.recepcion_esperada_creada:'.$resguardoId.':'.$pedidoBmaId;
    }
}
