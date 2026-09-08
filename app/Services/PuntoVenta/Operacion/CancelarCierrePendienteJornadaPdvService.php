<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Events\PuntoVenta\JornadaAbierta;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelarCierrePendienteJornadaPdvService
{
    use ResuelveConcurrenciaJornadaPdv;

    /**
     * @return array{jornada: JornadaPdv, intervalo: IntervaloOperativoPdv, reintento: bool}
     */
    public function cancelarParaUsuario(
        int $userId,
        int $sucursalId,
        int $versionEsperada,
        CarbonInterface $ahora,
        int $actorId,
    ): array {
        return DB::transaction(function () use ($userId, $sucursalId, $versionEsperada, $ahora, $actorId): array {
            $jornada = JornadaPdv::query()
                ->where('user_id', $userId)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', EstadoJornadaPdv::CerradaConAtencion)
                ->lockForUpdate()
                ->first();

            if (! $jornada instanceof JornadaPdv) {
                throw ValidationException::withMessages([
                    'jornada' => 'No hay un cierre pendiente para cancelar.',
                ]);
            }

            $this->assertVersionJornada($jornada, $versionEsperada);

            $versionAnterior = (int) $jornada->version;
            $actualizado = JornadaPdv::query()
                ->whereKey($jornada->id)
                ->where('version', $versionAnterior)
                ->update([
                    'estado' => EstadoJornadaPdv::Abierta,
                    'cierre_at' => null,
                    'version' => $versionAnterior + 1,
                ]);

            if ($actualizado !== 1) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó la jornada. Actualice la página e intente de nuevo.',
                ]);
            }

            $jornadaActualizada = $jornada->fresh();

            $intervalo = IntervaloOperativoPdv::query()
                ->where('jornada_id', $jornadaActualizada->id)
                ->whereNull('fin_at')
                ->first();

            $tieneAtencionAbierta = TurnoPdvAtencion::query()
                ->where('user_id', $userId)
                ->whereNull('fin_at')
                ->exists();

            if (! $intervalo instanceof IntervaloOperativoPdv && ! $tieneAtencionAbierta) {
                $intervalo = IntervaloOperativoPdv::query()
                    ->where('jornada_id', $jornadaActualizada->id)
                    ->latest('id')
                    ->first();

                if ($intervalo instanceof IntervaloOperativoPdv) {
                    $intervalo->tipo = TipoIntervaloOperativoPdv::Disponible;
                    $intervalo->inicio_at = $ahora;
                    $intervalo->fin_at = null;
                    $intervalo->save();
                } else {
                    $intervalo = IntervaloOperativoPdv::query()->create([
                        'jornada_id' => $jornadaActualizada->id,
                        'user_id' => $userId,
                        'sucursal_id' => $sucursalId,
                        'tipo' => TipoIntervaloOperativoPdv::Disponible,
                        'inicio_at' => $ahora,
                        'version' => 1,
                    ]);
                }
            }

            if ($intervalo instanceof IntervaloOperativoPdv) {
                JornadaAbierta::dispatch(
                    $jornadaActualizada,
                    $intervalo,
                    $sucursalId,
                    $actorId,
                );
            }

            return [
                'jornada' => $jornadaActualizada,
                'intervalo' => $intervalo ?? new IntervaloOperativoPdv,
                'reintento' => false,
            ];
        });
    }
}
