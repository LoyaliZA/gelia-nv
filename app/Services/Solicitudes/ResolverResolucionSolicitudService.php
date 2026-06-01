<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;

class ResolverResolucionSolicitudService
{
    private const ESTADOS_FINALES = [2, 3, 4, 5];

    public function resolver(SolicitudTag $solicitud): array
    {
        $auditorias = collect($solicitud->auditorias ?? []);

        $resolucion = $auditorias
            ->whereIn('estado_nuevo_id', self::ESTADOS_FINALES)
            ->sortByDesc('id')
            ->first();

        $ultimaNota = $auditorias
            ->sortByDesc('id')
            ->first(fn ($a) => ! str_contains(strtoupper($a->motivo_reporte ?? ''), 'AUTOMÁTICAMENTE'));

        $estadoNombre = $solicitud->estado?->nombre ?? 'Pendiente';

        if (strtolower($estadoNombre) === 'pendiente') {
            return [
                'respuesta' => 'Pendiente',
                'fecha_resolucion' => null,
            ];
        }

        $motivo = trim($ultimaNota?->motivo_reporte ?? '');
        $respuesta = $motivo !== ''
            ? "{$estadoNombre}: {$motivo}"
            : $estadoNombre;

        return [
            'respuesta' => $respuesta,
            'fecha_resolucion' => $resolucion?->created_at,
        ];
    }
}
