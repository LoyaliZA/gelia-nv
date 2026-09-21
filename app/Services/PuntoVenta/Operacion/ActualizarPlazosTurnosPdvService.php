<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Validation\ValidationException;

class ActualizarPlazosTurnosPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    /**
     * @param  array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int
     * }  $datos
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int
     * }
     */
    public function ejecutar(User $actor, array $datos): array
    {
        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'sucursal' => 'Debe seleccionar una sucursal activa.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
            $sucursalId,
        );

        return $this->plazos->persistir($datos);
    }
}
