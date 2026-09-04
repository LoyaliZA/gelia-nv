<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class ResolverUmbralCierreSucursalPdv
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

        $horario = $this->horario->resolverParaSucursal($sucursalId);
        if ($horario === null) {
            return ['aplicable' => false];
        }

        $zona = $horario['zona_horaria'];
        $ahoraLocal = $ahora->copy()->timezone($zona);
        $fechaOperativa = $this->operacion->fechaOperativa($sucursalId, $ahoraLocal);

        if ($dia->fecha_operativa->toDateString() !== $fechaOperativa) {
            return ['aplicable' => false];
        }

        if ($dia->ampliacion_hasta_at !== null) {
            $ampliacionLocal = $dia->ampliacion_hasta_at->copy()->timezone($zona);
            if ($ahoraLocal->lt($ampliacionLocal)) {
                return ['aplicable' => false];
            }

            return $this->resultadoAplicable(
                $ampliacionLocal,
                $horario,
                'ampliacion',
                $dia->ampliacion_hasta_at->toIso8601String(),
            );
        }

        [$hora, $minuto] = array_map('intval', explode(':', $horario['hora_cierre']));
        $umbral = Carbon::parse($fechaOperativa, $zona)->setTime($hora, $minuto, 0);

        if ($ahoraLocal->lt($umbral)) {
            return ['aplicable' => false];
        }

        return $this->resultadoAplicable($umbral, $horario, 'configuracion');
    }

    /**
     * @param  array{zona_horaria: string, hora_cierre: string}  $horario
     * @return array{
     *   aplicable: bool,
     *   umbral: CarbonInterface,
     *   snapshot: array<string, mixed>
     * }
     */
    private function resultadoAplicable(
        CarbonInterface $umbral,
        array $horario,
        string $origenUmbral,
        ?string $ampliacionHastaAt = null,
    ): array {
        return [
            'aplicable' => true,
            'umbral' => $umbral,
            'snapshot' => [
                'hora_cierre_configurada' => $horario['hora_cierre'],
                'zona_horaria' => $horario['zona_horaria'],
                'umbral_aplicado_at' => $umbral->toIso8601String(),
                'origen_umbral' => $origenUmbral,
                'ampliacion_hasta_at' => $ampliacionHastaAt,
            ],
        ];
    }
}
