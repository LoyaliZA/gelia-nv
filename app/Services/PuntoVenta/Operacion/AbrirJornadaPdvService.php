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
            $existente = $this->jornadaActivaAbierta((int) $actor->id, $sucursalId);
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

            if ($this->jornadaActiva((int) $actor->id, $sucursalId) instanceof JornadaPdv) {
                throw ValidationException::withMessages([
                    'jornada' => 'Ya existe una jornada activa que no puede reabrirse.',
                ]);
            }

            try {
                $jornada = JornadaPdv::query()->create([
                    'user_id' => $actor->id,
                    'sucursal_id' => $sucursalId,
                    'estado' => EstadoJornadaPdv::Abierta,
                    'apertura_at' => $ahora,
                    'version' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                $recuperada = $this->jornadaActivaAbierta((int) $actor->id, $sucursalId);
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
                'user_id' => $actor->id,
                'sucursal_id' => $sucursalId,
                'tipo' => TipoIntervaloOperativoPdv::Disponible,
                'inicio_at' => $ahora,
                'version' => 1,
            ]);

            JornadaAbierta::dispatch($jornada->fresh(), $intervalo, $sucursalId, (int) $actor->id);

            return [
                'jornada' => $jornada->fresh(),
                'intervalo' => $intervalo,
                'reintento' => false,
            ];
        });
    }
}
