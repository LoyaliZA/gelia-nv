<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\PausaFinalizada;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizarPausaPdvService
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
            return $this->finalizarParaUsuario((int) $actor->id, $sucursalId, $ahora, (int) $actor->id);
        });
    }

    /**
     * @return array{jornada: JornadaPdv, intervalo: IntervaloOperativoPdv, reintento: bool}
     */
    public function finalizarParaUsuario(int $userId, int $sucursalId, CarbonInterface $ahora, int $actorId): array
    {
        return DB::transaction(function () use ($userId, $sucursalId, $ahora, $actorId): array {
            $jornada = JornadaPdv::query()
                ->where('user_id', $userId)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', EstadoJornadaPdv::Abierta)
                ->lockForUpdate()
                ->first();

            if (! $jornada instanceof JornadaPdv) {
                throw ValidationException::withMessages([
                    'jornada' => 'No hay una jornada abierta para finalizar la pausa.',
                ]);
            }

            $intervaloAbierto = IntervaloOperativoPdv::query()
                ->where('jornada_id', $jornada->id)
                ->whereNull('fin_at')
                ->lockForUpdate()
                ->first();

            if ($intervaloAbierto?->tipo === TipoIntervaloOperativoPdv::EnPausa) {
                $intervaloAbierto->update([
                    'fin_at' => $ahora,
                    'pausa_finalizada_por_id' => $actorId,
                ]);

                $intervaloDisponible = IntervaloOperativoPdv::query()->create([
                    'jornada_id' => $jornada->id,
                    'user_id' => $userId,
                    'sucursal_id' => $sucursalId,
                    'tipo' => TipoIntervaloOperativoPdv::Disponible,
                    'inicio_at' => $ahora,
                    'version' => 1,
                ]);

                PausaFinalizada::dispatch(
                    $jornada->fresh(),
                    $intervaloDisponible,
                    $sucursalId,
                    $actorId,
                );

                return [
                    'jornada' => $jornada,
                    'intervalo' => $intervaloDisponible,
                    'reintento' => false,
                ];
            }

            if ($intervaloAbierto?->tipo === TipoIntervaloOperativoPdv::Disponible
                && IntervaloOperativoPdv::query()
                    ->where('jornada_id', $jornada->id)
                    ->where('tipo', TipoIntervaloOperativoPdv::EnPausa)
                    ->where('fin_at', '>=', $ahora->copy()->subSeconds(5))
                    ->exists()) {
                return [
                    'jornada' => $jornada,
                    'intervalo' => $intervaloAbierto,
                    'reintento' => true,
                ];
            }

            throw ValidationException::withMessages([
                'pausa' => 'No hay una pausa activa para finalizar.',
            ]);
        });
    }
}
