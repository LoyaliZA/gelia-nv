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
        private readonly ConsultaMotivosPausaPdvService $motivosPausa,
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
            return $this->iniciarParaUsuario((int) $actor->id, $sucursalId, $ahora, (int) $actor->id);
        });
    }

    /**
     * @return array{jornada: JornadaPdv, intervalo: IntervaloOperativoPdv, reintento: bool}
     */
    public function iniciarParaUsuario(
        int $userId,
        int $sucursalId,
        CarbonInterface $ahora,
        int $actorId,
        ?int $motivoPausaId = null,
        ?string $motivoDetalle = null,
        bool $requiereMotivo = false,
    ): array {
        $motivo = null;
        $detalle = null;

        if ($requiereMotivo) {
            if ($motivoPausaId === null) {
                throw ValidationException::withMessages([
                    'motivo_pausa_id' => 'Debe seleccionar un motivo de pausa.',
                ]);
            }

            $resuelto = $this->motivosPausa->resolverParaInicio($motivoPausaId, $motivoDetalle);
            $motivo = $resuelto['motivo'];
            $detalle = $resuelto['detalle'];
        }

        return DB::transaction(function () use ($userId, $sucursalId, $ahora, $actorId, $motivo, $detalle): array {
            $jornada = JornadaPdv::query()
                ->where('user_id', $userId)
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
                ->where('user_id', $userId)
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
                'user_id' => $userId,
                'sucursal_id' => $sucursalId,
                'tipo' => TipoIntervaloOperativoPdv::EnPausa,
                'motivo_pausa_id' => $motivo?->id,
                'motivo_detalle' => $detalle,
                'pausa_iniciada_por_id' => $actorId,
                'inicio_at' => $ahora,
                'version' => 1,
            ]);

            PausaIniciada::dispatch(
                $jornada->fresh(),
                $intervaloPausa,
                $sucursalId,
                $actorId,
            );

            return [
                'jornada' => $jornada,
                'intervalo' => $intervaloPausa,
                'reintento' => false,
            ];
        });
    }
}
