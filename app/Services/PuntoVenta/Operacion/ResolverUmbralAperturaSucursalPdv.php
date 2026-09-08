<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class ResolverUmbralAperturaSucursalPdv
{
    public function __construct(
        private readonly HorarioCierreOperacionPdvConfig $horario,
        private readonly OperacionPdvConfig $operacion,
    ) {}

    /**
     * @return array{
     *   aplicable: bool,
     *   umbral?: CarbonInterface,
     *   snapshot?: array<string, mixed>
     * }
     */
    public function evaluar(int $sucursalId, SucursalDiaOperacionPdv $dia, CarbonInterface $ahora): array
    {
        if ($dia->cierre_manual_at !== null) {
            return ['aplicable' => false];
        }

        if ($dia->acepta_altas) {
            return ['aplicable' => false];
        }

        $horario = $this->horario->resolverParaSucursal($sucursalId);
        if ($horario === null || $horario['hora_apertura'] === null) {
            return ['aplicable' => false];
        }

        $zona = $horario['zona_horaria'];
        $ahoraLocal = $ahora->copy()->timezone($zona);
        $fechaOperativa = $this->operacion->fechaOperativa($sucursalId, $ahoraLocal);

        if ($dia->fecha_operativa->toDateString() !== $fechaOperativa) {
            return ['aplicable' => false];
        }

        [$hora, $minuto] = array_map('intval', explode(':', $horario['hora_apertura']));
        $umbral = Carbon::parse($fechaOperativa, $zona)->setTime($hora, $minuto, 0);

        if ($ahoraLocal->lt($umbral)) {
            return ['aplicable' => false];
        }

        return [
            'aplicable' => true,
            'umbral' => $umbral,
            'snapshot' => [
                'hora_apertura_configurada' => $horario['hora_apertura'],
                'zona_horaria' => $horario['zona_horaria'],
                'umbral_aplicado_at' => $umbral->toIso8601String(),
            ],
        ];
    }
}
