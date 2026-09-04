<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\PausaIniciada;
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

class IniciarPausaPdvService
{
    use ResuelveConcurrenciaJornadaPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{jornada: JornadaPdv, intervalo: IntervaloOperativoPdv, reintento: bool}
     */
    public function ejecutar(User $actor, CarbonInterface $ahora): array
    {
        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe seleccionar una sucursal activa.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
            $sucursalId,
        );

        return DB::transaction(function () use ($actor, $sucursalId, $ahora): array {
            $jornada = JornadaPdv::query()
                ->where('user_id', $actor->id)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', EstadoJornadaPdv::Abierta)
                ->lockForUpdate()
                ->first();

            if (! $jornada instanceof JornadaPdv) {
                throw ValidationException::withMessages([
                    'jornada' => 'No hay una jornada abierta para pausar.',
                ]);
            }

            $intervaloAbierto = IntervaloOperativoPdv::query()
                ->where('jornada_id', $jornada->id)
                ->whereNull('fin_at')
                ->lockForUpdate()
                ->first();

            if ($intervaloAbierto?->tipo === TipoIntervaloOperativoPdv::EnPausa) {
                return [
                    'jornada' => $jornada,
                    'intervalo' => $intervaloAbierto,
                    'reintento' => true,
                ];
            }

            if (TurnoPdvAtencion::query()
                ->where('user_id', $actor->id)
                ->whereNull('fin_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'atencion' => 'No puede pausar con una atención abierta.',
                ]);
            }

            if (! $intervaloAbierto instanceof IntervaloOperativoPdv
                || $intervaloAbierto->tipo !== TipoIntervaloOperativoPdv::Disponible) {
                throw ValidationException::withMessages([
                    'actividad' => 'Solo puede pausar estando disponible.',
                ]);
            }

            $intervaloAbierto->update(['fin_at' => $ahora]);

            $intervaloPausa = IntervaloOperativoPdv::query()->create([
                'jornada_id' => $jornada->id,
                'user_id' => $actor->id,
                'sucursal_id' => $sucursalId,
                'tipo' => TipoIntervaloOperativoPdv::EnPausa,
                'inicio_at' => $ahora,
                'version' => 1,
            ]);

            PausaIniciada::dispatch(
                $jornada->fresh(),
                $intervaloPausa,
                $sucursalId,
                (int) $actor->id,
            );

            return [
                'jornada' => $jornada,
                'intervalo' => $intervaloPausa,
                'reintento' => false,
            ];
        });
    }
}
