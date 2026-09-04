<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\JornadaAmpliada;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmpliarHorarioSucursalPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ResolverSucursalDiaOperacionPdv $sucursalDia,
        private readonly OperacionPdvConfig $config,
    ) {}

    public function ejecutar(
        User $actor,
        int $versionEsperada,
        CarbonInterface $ampliacionHasta,
        CarbonInterface $ahora,
    ): SucursalDiaOperacionPdv {
        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe seleccionar una sucursal activa.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR,
            $sucursalId,
        );

        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);
        if ($ampliacionHasta->toDateString() !== $fechaOperativa) {
            throw ValidationException::withMessages([
                'ampliacion_hasta_at' => 'La ampliación debe aplicar al día operativo actual.',
            ]);
        }

        if ($ampliacionHasta->lessThanOrEqualTo($ahora)) {
            throw ValidationException::withMessages([
                'ampliacion_hasta_at' => 'La ampliación debe ser posterior al momento actual.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $sucursalId,
            $versionEsperada,
            $ampliacionHasta,
            $ahora,
        ): SucursalDiaOperacionPdv {
            $dia = SucursalDiaOperacionPdv::query()
                ->whereKey($this->sucursalDia->obtenerOCrear($sucursalId, $ahora)->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $dia->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó el estado del día. Actualice la página e intente de nuevo.',
                ]);
            }

            $dia->aplicarAmpliacion($actor, $ampliacionHasta, $ahora);
            $dia->version = (int) $dia->version + 1;
            $dia->save();

            JornadaAmpliada::dispatch($dia->fresh(), $sucursalId, (int) $actor->id);

            return $dia->fresh();
        });
    }
}
