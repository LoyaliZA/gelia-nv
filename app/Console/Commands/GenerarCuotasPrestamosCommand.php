<?php

namespace App\Console\Commands;

use App\Models\RhConfiguracion;
use App\Services\Rh\GenerarCuotasPrestamoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerarCuotasPrestamosCommand extends Command
{
    protected $signature = 'rh:generar-cuotas-prestamos';

    protected $description = 'Genera cuotas de préstamos y pagos fijos activos para el periodo de pago actual.';

    public function handle(GenerarCuotasPrestamoService $generarService): int
    {
        $config = RhConfiguracion::obtener();
        $diasPeriodo = max(1, (int) $config->dias_periodo_pago);

        $fechaFin = now();
        $fechaInicio = $fechaFin->copy()->subDays($diasPeriodo - 1);

        $resultado = $generarService->ejecutar($fechaInicio, $fechaFin);

        $this->info("Cuotas generadas: {$resultado['generadas']}. Omitidas: {$resultado['omitidas']}.");

        foreach ($resultado['errores'] as $error) {
            $this->error("{$error['folio']}: {$error['mensaje']}");
        }

        return empty($resultado['errores']) ? self::SUCCESS : self::FAILURE;
    }
}
