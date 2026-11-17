<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Validation\ValidationException;

class ActualizarHorarioCierreSucursalPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly HorarioCierreOperacionPdvConfig $horario,
    ) {}

    /**
     * @param  array{
     *   hora_apertura: string|null,
     *   hora_cierre: string,
     *   zona_horaria: string|null,
     *   dias_operacion: list<int>|null,
     *   hora_apertura_sabado: string|null,
     *   hora_cierre_sabado: string|null
     * }  $datos
     * @return array<string, mixed>
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
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR,
            $sucursalId,
        );

        $global = $this->horario->obtenerGlobal() ?? $this->horario->configuracionInicialPlaneada();
        $claveSucursal = (string) $sucursalId;
        $override = $global['por_sucursal'][$claveSucursal] ?? [];
        if (! is_array($override)) {
            $override = [];
        }

        $override['hora_cierre'] = $datos['hora_cierre'];
        $override['hora_apertura'] = $datos['hora_apertura'];
        $override['hora_apertura_sabado'] = $datos['hora_apertura_sabado'];
        $override['hora_cierre_sabado'] = $datos['hora_cierre_sabado'];
        if ($datos['dias_operacion'] !== null) {
            $override['dias_operacion'] = $datos['dias_operacion'];
        }
        if ($datos['zona_horaria'] !== null && trim($datos['zona_horaria']) !== '') {
            $override['zona_horaria'] = trim($datos['zona_horaria']);
        }

        $global['por_sucursal'][$claveSucursal] = $override;
        $this->horario->persistir($global);

        $serializado = $this->horario->serializarParaSucursal($sucursalId);
        $serializado['es_override_sucursal'] = true;

        return $serializado;
    }
}
