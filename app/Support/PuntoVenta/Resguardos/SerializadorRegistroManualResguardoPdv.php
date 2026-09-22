<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Services\PuntoVenta\Resguardos\CrearResguardoManualPdvService;

final class SerializadorRegistroManualResguardoPdv
{
    /**
     * @return array<string, mixed>|null
     */
    public static function desdeResguardo(ResguardoPdv $resguardo): ?array
    {
        $snapshot = is_array($resguardo->snapshot_json) ? $resguardo->snapshot_json : [];
        if (($snapshot['handoff'] ?? null) !== CrearResguardoManualPdvService::HANDOFF) {
            return null;
        }

        $evidencias = [];
        if ($resguardo->relationLoaded('evidencias')) {
            foreach ($resguardo->evidencias as $evidencia) {
                $uso = $evidencia->metadata_json['uso'] ?? null;
                if (! in_array($uso, [ResguardoPdvEvidencia::USO_TICKET, ResguardoPdvEvidencia::USO_PAQUETE], true)) {
                    continue;
                }

                $evidencias[] = self::serializarEvidencia($evidencia, $uso);
            }
        }

        return [
            'origen_id' => isset($snapshot['origen_id']) ? (int) $snapshot['origen_id'] : null,
            'origen_nombre' => isset($snapshot['origen_nombre']) ? (string) $snapshot['origen_nombre'] : null,
            'observaciones' => isset($snapshot['observaciones']) ? (string) $snapshot['observaciones'] : null,
            'cantidad_piezas' => isset($snapshot['cantidad_piezas']) ? (int) $snapshot['cantidad_piezas'] : null,
            'piezas' => is_array($snapshot['piezas'] ?? null) ? $snapshot['piezas'] : [],
            'evidencias' => $evidencias,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializarEvidencia(ResguardoPdvEvidencia $evidencia, string $uso): array
    {
        return [
            'id' => $evidencia->id,
            'uso' => $uso,
            'tipo' => $evidencia->tipo,
            'nombre_original' => $evidencia->nombre_original,
            'mime_type' => $evidencia->mime_type,
            'ruta_publica' => $evidencia->tipo === ResguardoPdvEvidencia::TIPO_FIRMA
                ? null
                : '/storage/'.$evidencia->ruta_interna,
        ];
    }
}
