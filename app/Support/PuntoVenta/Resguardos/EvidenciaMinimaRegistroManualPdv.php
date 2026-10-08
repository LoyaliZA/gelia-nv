<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Services\PuntoVenta\Resguardos\CrearResguardoManualPdvService;
use Illuminate\Database\Eloquent\Builder;

final class EvidenciaMinimaRegistroManualPdv
{
    /** @var list<string> */
    public const USOS = [
        ResguardoPdvEvidencia::USO_TICKET,
        ResguardoPdvEvidencia::USO_PAQUETE,
    ];

    public static function esManual(ResguardoPdv $resguardo): bool
    {
        $snapshot = is_array($resguardo->snapshot_json) ? $resguardo->snapshot_json : [];

        return ($snapshot['handoff'] ?? null) === CrearResguardoManualPdvService::HANDOFF;
    }

    public static function completa(ResguardoPdv $resguardo): bool
    {
        if (! self::esManual($resguardo)) {
            return false;
        }

        $usos = self::usosPresentes($resguardo);

        foreach (self::USOS as $uso) {
            if (! in_array($uso, $usos, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function usosPresentes(ResguardoPdv $resguardo): array
    {
        $resguardo->loadMissing('evidencias');

        $usos = [];
        foreach ($resguardo->evidencias as $evidencia) {
            $uso = $evidencia->metadata_json['uso'] ?? null;
            if (is_string($uso) && in_array($uso, self::USOS, true)) {
                $usos[] = $uso;
            }
        }

        return array_values(array_unique($usos));
    }

    public static function restringirIncompleta(Builder $query): void
    {
        $query->where('snapshot_json->handoff', CrearResguardoManualPdvService::HANDOFF)
            ->where(function (Builder $manual) {
                foreach (self::USOS as $uso) {
                    $manual->orWhereDoesntHave(
                        'evidencias',
                        fn (Builder $evidencias) => $evidencias->where('metadata_json->uso', $uso)
                    );
                }
            });
    }

    public static function restringirCompleta(Builder $query): void
    {
        $query->where('snapshot_json->handoff', CrearResguardoManualPdvService::HANDOFF);
        foreach (self::USOS as $uso) {
            $query->whereHas(
                'evidencias',
                fn (Builder $evidencias) => $evidencias->where('metadata_json->uso', $uso)
            );
        }
    }
}
