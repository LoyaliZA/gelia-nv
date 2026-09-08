<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\JornadaAbierta;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AbrirJornadaPdvService
{
    use ResuelveConcurrenciaJornadaPdv;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly OperacionPdvConfig $config,
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
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR,
            $sucursalId,
        );

        return DB::transaction(function () use ($actor, $sucursalId, $ahora): array {
            return $this->abrirParaUsuario((int) $actor->id, $sucursalId, $ahora, (int) $actor->id);
        });
    }

    /**
     * @return array{jornada: JornadaPdv, intervalo: IntervaloOperativoPdv, reintento: bool}
     */
    public function abrirParaUsuario(int $userId, int $sucursalId, CarbonInterface $ahora, int $actorId): array
    {
        return DB::transaction(function () use ($userId, $sucursalId, $ahora, $actorId): array {
            $existente = $this->jornadaActivaAbierta($userId, $sucursalId);
            if ($existente instanceof JornadaPdv) {
                $intervalo = IntervaloOperativoPdv::query()
                    ->where('jornada_id', $existente->id)
                    ->whereNull('fin_at')
                    ->first();

                return [
                    'jornada' => $existente,
                    'intervalo' => $intervalo ?? new IntervaloOperativoPdv,
                    'reintento' => true,
                ];
            }

            if ($this->jornadaActiva($userId, $sucursalId) instanceof JornadaPdv) {
                throw ValidationException::withMessages([
                    'jornada' => 'Ya existe una jornada activa que no puede reabrirse.',
                ]);
            }

            try {
                $jornada = JornadaPdv::query()->create([
                    'user_id' => $userId,
                    'sucursal_id' => $sucursalId,
                    'estado' => EstadoJornadaPdv::Abierta,
                    'apertura_at' => $ahora,
                    'version' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                $recuperada = $this->jornadaActivaAbierta($userId, $sucursalId);
                if ($recuperada instanceof JornadaPdv) {
                    $intervalo = IntervaloOperativoPdv::query()
                        ->where('jornada_id', $recuperada->id)
                        ->whereNull('fin_at')
                        ->first();

                    return [
                        'jornada' => $recuperada,
                        'intervalo' => $intervalo ?? new IntervaloOperativoPdv,
                        'reintento' => true,
                    ];
                }

                throw ValidationException::withMessages([
                    'jornada' => 'No fue posible abrir la jornada por concurrencia.',
                ]);
            }

            $intervalo = IntervaloOperativoPdv::query()->create([
                'jornada_id' => $jornada->id,
                'user_id' => $userId,
                'sucursal_id' => $sucursalId,
                'tipo' => TipoIntervaloOperativoPdv::Disponible,
                'inicio_at' => $ahora,
                'version' => 1,
            ]);

            JornadaAbierta::dispatch($jornada->fresh(), $intervalo, $sucursalId, $actorId);

            return [
                'jornada' => $jornada->fresh(),
                'intervalo' => $intervalo,
                'reintento' => false,
            ];
        });
    }

    /**
     * @return array{jornada: JornadaPdv, intervalo: IntervaloOperativoPdv, reintento: bool}
     */
    public function reactivarParaUsuario(int $userId, int $sucursalId, CarbonInterface $ahora, int $actorId): array
    {
        return DB::transaction(function () use ($userId, $sucursalId, $ahora, $actorId): array {
            $existente = $this->jornadaActivaAbierta($userId, $sucursalId);
            if ($existente instanceof JornadaPdv) {
                $intervalo = IntervaloOperativoPdv::query()
                    ->where('jornada_id', $existente->id)
                    ->whereNull('fin_at')
                    ->first();

                return [
                    'jornada' => $existente,
                    'intervalo' => $intervalo ?? new IntervaloOperativoPdv,
                    'reintento' => true,
                ];
            }

            if ($this->jornadaActiva($userId, $sucursalId) instanceof JornadaPdv) {
                throw ValidationException::withMessages([
                    'jornada' => 'Ya existe una jornada activa que no puede reactivarse.',
                ]);
            }

            $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);

            $jornada = JornadaPdv::query()
                ->where('user_id', $userId)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', EstadoJornadaPdv::Cerrada)
                ->whereDate('apertura_at', $fechaOperativa)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $jornada instanceof JornadaPdv) {
                throw ValidationException::withMessages([
                    'jornada' => 'No hay una jornada cerrada hoy que pueda reactivarse.',
                ]);
            }

            $jornada->estado = EstadoJornadaPdv::Abierta;
            $jornada->cierre_at = null;
            $jornada->version = (int) $jornada->version + 1;
            $jornada->save();

            $intervalo = IntervaloOperativoPdv::query()
                ->where('jornada_id', $jornada->id)
                ->whereNull('fin_at')
                ->first();

            if (! $intervalo instanceof IntervaloOperativoPdv) {
                $intervalo = $this->reabrirIntervaloDisponible($jornada, $userId, $sucursalId, $ahora);
            }

            JornadaAbierta::dispatch($jornada->fresh(), $intervalo, $sucursalId, $actorId);

            return [
                'jornada' => $jornada->fresh(),
                'intervalo' => $intervalo,
                'reintento' => false,
            ];
        });
    }

    private function reabrirIntervaloDisponible(
        JornadaPdv $jornada,
        int $userId,
        int $sucursalId,
        CarbonInterface $ahora,
    ): IntervaloOperativoPdv {
        $cerrado = IntervaloOperativoPdv::query()
            ->where('jornada_id', $jornada->id)
            ->latest('id')
            ->first();

        if ($cerrado instanceof IntervaloOperativoPdv) {
            $cerrado->tipo = TipoIntervaloOperativoPdv::Disponible;
            $cerrado->inicio_at = $ahora;
            $cerrado->fin_at = null;
            $cerrado->save();

            return $cerrado;
        }

        return IntervaloOperativoPdv::query()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $userId,
            'sucursal_id' => $sucursalId,
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
            'inicio_at' => $ahora,
            'version' => 1,
        ]);
    }
}
