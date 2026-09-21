<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\JornadaAperturaManual;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AperturaManualSucursalPdvService
{
    public const TIPO_EVENTO = 'jornada.apertura_manual';

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ResolverSucursalDiaOperacionPdv $sucursalDia,
    ) {}

    public function ejecutar(User $actor, int $versionEsperada, CarbonInterface $ahora): SucursalDiaOperacionPdv
    {
        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe seleccionar una sucursal activa.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL,
            $sucursalId,
        );

        $eventoPendiente = null;

        $dia = DB::transaction(function () use (
            $actor,
            $sucursalId,
            $versionEsperada,
            $ahora,
            &$eventoPendiente,
        ): SucursalDiaOperacionPdv {
            $dia = $this->bloquearDia($sucursalId, $ahora);

            if ((int) $dia->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó el estado del día. Actualice la página e intente de nuevo.',
                ]);
            }

            if ($dia->cierre_manual_at !== null) {
                throw ValidationException::withMessages([
                    'sucursal' => 'La sucursal tiene un cierre manual. Reábrela antes de iniciar la jornada.',
                ]);
            }

            if ($dia->acepta_altas) {
                return $dia;
            }

            $dia->aplicaAperturaManual($actor, $ahora);
            $dia->version = (int) $dia->version + 1;
            $dia->save();

            try {
                $eventoPendiente = OperacionPdvEvento::query()->create([
                    'sucursal_dia_id' => $dia->id,
                    'sucursal_id' => $sucursalId,
                    'tipo_evento' => self::TIPO_EVENTO,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'origen' => 'manual',
                        'actor_id' => $actor->id,
                    ],
                    'idempotency_key' => $this->idempotencyKey(
                        $sucursalId,
                        $dia->fecha_operativa->toDateString(),
                    ),
                ]);
            } catch (UniqueConstraintViolationException) {
                return $dia->fresh();
            }

            return $dia->fresh();
        });

        if ($eventoPendiente instanceof OperacionPdvEvento) {
            JornadaAperturaManual::dispatch($dia, $eventoPendiente, $sucursalId, (int) $actor->id);
        }

        return $dia;
    }

    private function bloquearDia(int $sucursalId, CarbonInterface $ahora): SucursalDiaOperacionPdv
    {
        $referencia = $this->sucursalDia->obtenerOCrear($sucursalId, $ahora);

        return SucursalDiaOperacionPdv::query()
            ->whereKey($referencia->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function idempotencyKey(int $sucursalId, string $fechaOperativa): string
    {
        return 'pdv:apertura-manual:'.$sucursalId.':'.$fechaOperativa;
    }
}
