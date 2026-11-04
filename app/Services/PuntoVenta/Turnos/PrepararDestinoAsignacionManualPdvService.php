<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Services\PuntoVenta\Operacion\FinalizarPausaPdvService;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;

/**
 * Cierra la pausa de un destino manual y devuelve el momento en que puede iniciar la atención.
 */
class PrepararDestinoAsignacionManualPdvService
{
    public function __construct(
        private readonly FinalizarPausaPdvService $finalizarPausa,
    ) {}

    public function ejecutar(int $userId, int $sucursalId, CarbonInterface $ahora, int $actorId): CarbonInterface
    {
        $pausaAbierta = IntervaloOperativoPdv::query()
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->whereNull('fin_at')
            ->where('tipo', TipoIntervaloOperativoPdv::EnPausa)
            ->exists();

        if ($pausaAbierta) {
            $this->finalizarPausa->finalizarParaUsuario($userId, $sucursalId, $ahora, $actorId, false);
        }

        $jornada = JornadaPdv::query()
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->first();

        $disponibleDesde = $jornada?->disponible_desde;
        if ($disponibleDesde instanceof CarbonInterface && $disponibleDesde->greaterThan($ahora)) {
            return $disponibleDesde;
        }

        return $ahora->copy();
    }
}
