<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Support\PuntoVenta\Broadcast\Payloads\PayloadTurnoPdvBroadcast;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConsultaEstadoSalaPdvService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(int $sucursalId, CarbonInterface $ahora): array
    {
        $sucursal = Sucursal::query()
            ->whereKey($sucursalId)
            ->where('activo', true)
            ->first(['id', 'nombre']);

        if ($sucursal === null) {
            throw new NotFoundHttpException('Sucursal no disponible.');
        }

        $llamados = TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', TurnoPdv::ESTADO_ASIGNADO)
            ->whereHas('atencionActual', static function ($query): void {
                $query->whereNull('fin_at');
            })
            ->with(['atencionActual.user'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(static function (TurnoPdv $turno): array {
                $atencion = $turno->atencionActual;

                return array_merge(
                    PayloadTurnoPdvBroadcast::publico($turno, $atencion),
                    [
                        'llamado_at' => $atencion?->inicio_at?->toIso8601String(),
                    ],
                );
            })
            ->values()
            ->all();

        return [
            'servidor_at' => $ahora->toIso8601String(),
            'sucursal' => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ],
            'llamados' => $llamados,
        ];
    }
}
