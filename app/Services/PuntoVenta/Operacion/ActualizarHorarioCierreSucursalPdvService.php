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
     * @return array{
     *   configurado: bool,
     *   hora_cierre: string|null,
     *   zona_horaria: string|null,
     *   es_override_sucursal: bool
     * }
     */
    public function ejecutar(User $actor, string $horaCierre, ?string $zonaHoraria = null): array
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

        $override['hora_cierre'] = $horaCierre;
        if ($zonaHoraria !== null && trim($zonaHoraria) !== '') {
            $override['zona_horaria'] = trim($zonaHoraria);
        }

        $global['por_sucursal'][$claveSucursal] = $override;
        $this->horario->persistir($global);

        $efectivo = $this->horario->resolverParaSucursal($sucursalId);

        return [
            'configurado' => $efectivo !== null,
            'hora_cierre' => $efectivo['hora_cierre'] ?? null,
            'zona_horaria' => $efectivo['zona_horaria'] ?? null,
            'es_override_sucursal' => true,
        ];
    }
}
