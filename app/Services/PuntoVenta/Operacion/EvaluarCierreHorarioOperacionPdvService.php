<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Jobs\PuntoVenta\Operacion\CierreHorarioSucursalPdvJob;
use App\Models\Sucursal;
use Carbon\CarbonInterface;

class EvaluarCierreHorarioOperacionPdvService
{
    public function __construct(
        private readonly HorarioCierreOperacionPdvConfig $horario,
    ) {}

    /**
     * @return array{evaluadas: int, encoladas: int, omitidas: int}
     */
    public function ejecutar(?CarbonInterface $ahora = null): array
    {
        if (! $this->horario->estaConfigurado()) {
            return ['evaluadas' => 0, 'encoladas' => 0, 'omitidas' => 0];
        }

        $ahora = $ahora ?? now();
        $evaluadas = 0;
        $encoladas = 0;
        $omitidas = 0;

        Sucursal::query()
            ->where('activo', true)
            ->orderBy('id')
            ->chunkById(100, function ($sucursales) use ($ahora, &$evaluadas, &$encoladas, &$omitidas) {
                foreach ($sucursales as $sucursal) {
                    $evaluadas++;
                    $horario = $this->horario->resolverParaSucursal((int) $sucursal->id);
                    if ($horario === null) {
                        $omitidas++;

                        continue;
                    }

                    CierreHorarioSucursalPdvJob::dispatch((int) $sucursal->id, $ahora->toIso8601String());
                    $encoladas++;
                }
            });

        return [
            'evaluadas' => $evaluadas,
            'encoladas' => $encoladas,
            'omitidas' => $omitidas,
        ];
    }
}
