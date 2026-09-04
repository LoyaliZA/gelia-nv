<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\JornadaCierreManual;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CierreManualSucursalPdvService
{
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

        return DB::transaction(function () use ($actor, $sucursalId, $versionEsperada, $ahora): SucursalDiaOperacionPdv {
            $dia = $this->bloquearDia($sucursalId, $ahora);

            if ((int) $dia->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó el estado del día. Actualice la página e intente de nuevo.',
                ]);
            }

            if (! $dia->acepta_altas && $dia->cierre_manual_at !== null) {
                return $dia;
            }

            $dia->aplicaCierreManual($actor, $ahora);
            $dia->version = (int) $dia->version + 1;
            $dia->save();

            JornadaCierreManual::dispatch($dia->fresh(), $sucursalId, (int) $actor->id);

            return $dia->fresh();
        });
    }

    private function bloquearDia(int $sucursalId, CarbonInterface $ahora): SucursalDiaOperacionPdv
    {
        $referencia = $this->sucursalDia->obtenerOCrear($sucursalId, $ahora);

        return SucursalDiaOperacionPdv::query()
            ->whereKey($referencia->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
