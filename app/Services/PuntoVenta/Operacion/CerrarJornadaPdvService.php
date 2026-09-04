<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\JornadaCerrada;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CerrarJornadaPdvService
{
    use ResuelveConcurrenciaJornadaPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{jornada: JornadaPdv, estado_destino: EstadoJornadaPdv, reintento: bool}
     */
    public function ejecutar(User $actor, int $versionEsperada, CarbonInterface $ahora): array
    {
        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe seleccionar una sucursal activa.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR,
            $sucursalId,
        );

        return DB::transaction(function () use ($actor, $sucursalId, $versionEsperada, $ahora): array {
            $jornada = JornadaPdv::query()
                ->where('user_id', $actor->id)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', EstadoJornadaPdv::Abierta)
                ->lockForUpdate()
                ->first();

            if (! $jornada instanceof JornadaPdv) {
                $inactiva = JornadaPdv::query()
                    ->where('user_id', $actor->id)
                    ->where('sucursal_id', $sucursalId)
                    ->whereIn('estado', [
                        EstadoJornadaPdv::Cerrada,
                        EstadoJornadaPdv::CerradaConAtencion,
                    ])
                    ->where('cierre_at', '>=', $ahora->copy()->subSeconds(5))
                    ->latest('id')
                    ->first();

                if ($inactiva instanceof JornadaPdv) {
                    return [
                        'jornada' => $inactiva,
                        'estado_destino' => $inactiva->estado,
                        'reintento' => true,
                    ];
                }

                throw ValidationException::withMessages([
                    'jornada' => 'No hay una jornada abierta para cerrar.',
                ]);
            }

            $this->assertVersionJornada($jornada, $versionEsperada);

            $tieneAtencionAbierta = TurnoPdvAtencion::query()
                ->where('user_id', $actor->id)
                ->whereNull('fin_at')
                ->exists();

            $estadoDestino = $tieneAtencionAbierta
                ? EstadoJornadaPdv::CerradaConAtencion
                : EstadoJornadaPdv::Cerrada;

            $this->cerrarIntervalosNoAtencion($jornada, $ahora);

            $versionAnterior = (int) $jornada->version;
            $actualizado = JornadaPdv::query()
                ->whereKey($jornada->id)
                ->where('version', $versionAnterior)
                ->update([
                    'estado' => $estadoDestino,
                    'cierre_at' => $ahora,
                    'version' => $versionAnterior + 1,
                ]);

            if ($actualizado !== 1) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó la jornada. Actualice la página e intente de nuevo.',
                ]);
            }

            $jornadaActualizada = $jornada->fresh();

            JornadaCerrada::dispatch(
                $jornadaActualizada,
                $sucursalId,
                (int) $actor->id,
                'persona',
            );

            return [
                'jornada' => $jornadaActualizada,
                'estado_destino' => $estadoDestino,
                'reintento' => false,
            ];
        });
    }

    private function cerrarIntervalosNoAtencion(JornadaPdv $jornada, CarbonInterface $ahora): void
    {
        IntervaloOperativoPdv::query()
            ->where('jornada_id', $jornada->id)
            ->whereNull('fin_at')
            ->where('tipo', '!=', TipoIntervaloOperativoPdv::EnAtencion)
            ->update(['fin_at' => $ahora]);
    }
}
