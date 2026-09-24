<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\CrearResguardoManualPdvService;
use App\Support\PuntoVenta\Resguardos\UrlEvidenciaResguardoPdv;

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

        $departamentoNombre = isset($snapshot['departamento_nombre'])
            ? (string) $snapshot['departamento_nombre']
            : (isset($snapshot['origen_nombre']) ? (string) $snapshot['origen_nombre'] : null);

        return [
            'origen_id' => isset($snapshot['origen_id']) ? (int) $snapshot['origen_id'] : null,
            'origen_nombre' => isset($snapshot['origen_nombre']) ? (string) $snapshot['origen_nombre'] : null,
            'departamento_nombre' => $departamentoNombre,
            'registrado_por' => self::registradoPor($resguardo, $snapshot),
            'observaciones' => isset($snapshot['observaciones']) ? (string) $snapshot['observaciones'] : null,
            'cantidad_piezas' => isset($snapshot['cantidad_piezas']) ? (int) $snapshot['cantidad_piezas'] : null,
            'piezas' => is_array($snapshot['piezas'] ?? null) ? $snapshot['piezas'] : [],
            'evidencias' => $evidencias,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function registradoPor(ResguardoPdv $resguardo, array $snapshot): ?string
    {
        $desdeSnapshot = trim((string) ($snapshot['registrado_por_nombre'] ?? ''));
        if ($desdeSnapshot !== '') {
            return $desdeSnapshot;
        }

        if ($resguardo->relationLoaded('eventoRegistroManual')) {
            $actor = $resguardo->eventoRegistroManual?->actor;
            if ($actor instanceof User) {
                $nombre = trim((string) $actor->name);
                if ($nombre !== '') {
                    return $nombre;
                }

                if (filled($actor->username)) {
                    return '@'.$actor->username;
                }
            }
        }

        return null;
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
            'ruta_publica' => UrlEvidenciaResguardoPdv::url($evidencia),
        ];
    }
}
