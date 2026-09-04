<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Events\PuntoVenta\JornadaCierreHorario;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CierreHorarioSucursalPdvService
{
    public function __construct(
        private readonly ResolverSucursalDiaOperacionPdv $sucursalDia,
        private readonly ResolverUmbralCierreSucursalPdv $umbral,
    ) {}

    public function ejecutar(int $sucursalId, CarbonInterface $ahora): bool
    {
        $diaReferencia = $this->sucursalDia->obtenerOCrear($sucursalId, $ahora);
        $evaluacion = $this->umbral->evaluar($sucursalId, $diaReferencia, $ahora);

        if (! ($evaluacion['aplicable'] ?? false)) {
            return false;
        }

        /** @var CarbonInterface $umbral */
        $umbral = $evaluacion['umbral'];
        /** @var array<string, mixed> $snapshot */
        $snapshot = $evaluacion['snapshot'];
        $idempotencyKey = $this->idempotencyKey($sucursalId, $diaReferencia->fecha_operativa->toDateString(), $umbral);

        if (OperacionPdvEvento::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return false;
        }

        $eventoPendiente = null;
        $diaPendiente = null;

        $emitido = DB::transaction(function () use (
            $sucursalId,
            $ahora,
            $diaReferencia,
            $snapshot,
            $idempotencyKey,
            &$eventoPendiente,
            &$diaPendiente,
        ): bool {
            $dia = SucursalDiaOperacionPdv::query()
                ->whereKey($diaReferencia->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dia->cierre_manual_at !== null) {
                return false;
            }

            if (! $dia->acepta_altas) {
                return false;
            }

            $horaSnapshot = $this->resolverHoraSnapshot($snapshot);
            $dia->aplicarCierreHorario($ahora, $horaSnapshot);
            $dia->version = (int) $dia->version + 1;
            $dia->save();

            try {
                $eventoPendiente = OperacionPdvEvento::query()->create([
                    'sucursal_dia_id' => $dia->id,
                    'sucursal_id' => $sucursalId,
                    'tipo_evento' => OperacionPdvEvento::TIPO_CIERRE_HORARIO,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => $snapshot,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                return false;
            }

            $diaPendiente = $dia->fresh();

            return true;
        });

        if ($emitido && $eventoPendiente instanceof OperacionPdvEvento && $diaPendiente instanceof SucursalDiaOperacionPdv) {
            JornadaCierreHorario::dispatch($diaPendiente, $eventoPendiente, $sucursalId);
        }

        return $emitido;
    }

    private function idempotencyKey(int $sucursalId, string $fechaOperativa, CarbonInterface $umbral): string
    {
        return 'pdv:cierre-horario:'.$sucursalId.':'.$fechaOperativa.':'.$umbral->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function resolverHoraSnapshot(array $snapshot): string
    {
        if (($snapshot['origen_umbral'] ?? null) === 'ampliacion' && is_string($snapshot['ampliacion_hasta_at'] ?? null)) {
            return Carbon::parse($snapshot['ampliacion_hasta_at'])->format('H:i:s');
        }

        $horaConfigurada = (string) ($snapshot['hora_cierre_configurada'] ?? '00:00');

        return substr_count($horaConfigurada, ':') === 1
            ? $horaConfigurada.':00'
            : $horaConfigurada;
    }
}
