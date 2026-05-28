<?php

namespace App\Console\Commands;

use App\Models\Activo;
use App\Services\Activos\AlertasActivosService;
use App\Services\Activos\NotificarActivoService;
use Illuminate\Console\Command;

class EnviarAlertasActivosProgramadas extends Command
{
    protected $signature = 'activos:alertas-programadas';

    protected $description = 'Envía notificaciones programadas de vencimientos y mantenimiento de activos';

    public function handle(AlertasActivosService $alertasService, NotificarActivoService $notificarService): int
    {
        $alertas = $alertasService->ejecutar(null);

        $enviados = 0;

        foreach ($alertas['vencidos'] as $item) {
            $activo = Activo::with(['tipo', 'departamento', 'responsable'])->find($item['id']);
            if (!$activo) {
                continue;
            }

            $notificarService->ejecutar(
                $activo,
                'activo_vencimiento',
                'Activo vencido — requiere atención',
                null,
                null,
                null,
                ['fecha_alerta' => $item['fecha'] ?? null],
                true,
            );
            $enviados++;
        }

        foreach ($alertas['proximos_7'] as $item) {
            $activo = Activo::with(['tipo', 'departamento', 'responsable'])->find($item['id']);
            if (!$activo) {
                continue;
            }

            $notificarService->ejecutar(
                $activo,
                'activo_vencimiento',
                'Activo por vencer en los próximos 7 días',
                null,
                null,
                null,
                ['fecha_alerta' => $item['fecha'] ?? null],
                true,
            );
            $enviados++;
        }

        foreach ($alertas['mantenimiento'] as $item) {
            $activo = Activo::with(['tipo', 'departamento', 'responsable'])->find($item['id']);
            if (!$activo) {
                continue;
            }

            $notificarService->ejecutar(
                $activo,
                'activo_mantenimiento_proximo',
                'Mantenimiento próximo o pendiente',
                null,
                null,
                null,
                ['fecha_alerta' => $item['fecha'] ?? null],
                true,
            );
            $enviados++;
        }

        $totalAlertas = count($alertas['vencidos']) + count($alertas['proximos_7']) + count($alertas['mantenimiento']);
        $this->info("Alertas de activos procesadas: {$totalAlertas} ({$enviados} notificaciones enviadas).");

        return self::SUCCESS;
    }
}
