<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Support\PuntoVenta\Broadcast\Payloads\PayloadTurnoPdvBroadcast;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConsultaEstadoSalaPdvService
{
    public function __construct(
        private readonly ConsultaPlaylistPantallaSalaPdvService $playlist,
        private readonly ResolverColorPrimarioSalaPdvService $colorPrimario,
    ) {}

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

        $llamados = $this->llamadosActivos($sucursalId);
        $turnoActual = $llamados[0] ?? null;

        return [
            'servidor_at' => $ahora->toIso8601String(),
            'sucursal' => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ],
            'tema' => [
                'color_primario' => $this->colorPrimario->hex(),
            ],
            'llamados' => $llamados,
            'turno_actual' => $turnoActual,
            'proximos' => $this->proximos($sucursalId),
            'anteriores' => $this->anteriores($sucursalId, $ahora),
            'publicidad' => $this->playlist->paraSucursal($sucursalId, $ahora),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function llamadosActivos(int $sucursalId): array
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', TurnoPdv::ESTADO_ASIGNADO)
            ->whereHas('atencionActual', static function ($query): void {
                $query->whereNull('fin_at');
            })
            ->with(['atencionActual.user'])
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
            ->sortByDesc(static fn (array $item): string => (string) ($item['llamado_at'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function proximos(int $sucursalId): array
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('servicio', TurnoPdv::SERVICIO_VENTAS)
            ->where('estado', TurnoPdv::ESTADO_EN_COLA)
            ->whereNull('atencion_actual_id')
            ->orderByRaw(
                'CASE WHEN prioridad_adulto_mayor = 1'
                .' OR prioridad_discapacidad = 1'
                .' OR prioridad_diamante = 1'
                .' OR prioridad_vip = 1 THEN 0 ELSE 1 END ASC'
            )
            ->orderBy('alta_at')
            ->orderBy('id')
            ->limit(3)
            ->get()
            ->map(static fn (TurnoPdv $turno): array => [
                'turno_id' => $turno->id,
                'folio' => $turno->folio,
                'snapshot_nombre_llamado' => $turno->snapshot_nombre_llamado,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function anteriores(int $sucursalId, CarbonInterface $ahora): array
    {
        $inicioDia = $ahora->copy()->startOfDay();

        return TurnoPdvAtencion::query()
            ->whereNotNull('fin_at')
            ->where('fin_at', '>=', $inicioDia)
            ->where('es_transferencia', false)
            ->whereHas('turno', static function ($query) use ($sucursalId): void {
                $query->where('sucursal_id', $sucursalId)
                    ->whereIn('estado', [TurnoPdv::ESTADO_EN_REATENCION, TurnoPdv::ESTADO_CERRADO])
                    ->whereNull('baja_motivo');
            })
            ->with(['turno', 'user'])
            ->orderByDesc('fin_at')
            ->limit(12)
            ->get()
            ->unique('turno_id')
            ->take(4)
            ->map(static function (TurnoPdvAtencion $atencion): array {
                $turno = $atencion->turno;
                $publico = PayloadTurnoPdvBroadcast::publico($turno, $atencion);

                return [
                    'turno_id' => $publico['turno_id'],
                    'folio' => $publico['folio'],
                    'snapshot_nombre_llamado' => $publico['snapshot_nombre_llamado'],
                    'atendido_por' => $publico['atencion_nombre'],
                    'hora' => $atencion->fin_at?->timezone(config('app.timezone'))->format('H:i'),
                ];
            })
            ->values()
            ->all();
    }
}
