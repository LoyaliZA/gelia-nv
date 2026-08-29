<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Jobs\GenerarReportePagosPedidosPdfJob;
use App\Models\Reportes\ReportePagosPedidosExportacion;
use App\Models\User;
use App\Support\Reportes\ReportePagosPedidosProgreso;
use App\Support\Reportes\TituloReportePagosPedidos;
use Illuminate\Support\Str;

class SolicitarExportacionReportePagosPedidosService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{job_id: string, exportacion: array<string, mixed>}
     */
    public function ejecutar(User $usuario, array $filtros): array
    {
        $formato = $filtros['formato'] ?? 'pdf';
        $jobId = Str::uuid()->toString();

        $exportacion = ReportePagosPedidosExportacion::query()->create([
            'id' => $jobId,
            'user_id' => $usuario->id,
            'titulo' => TituloReportePagosPedidos::desdeFiltros($filtros),
            'formato' => $formato,
            'estado' => ReportePagosPedidosExportacion::ESTADO_PROCESSING,
            'filtros' => $filtros,
            'expira_at' => now()->addHours(48),
            'started_at' => now(),
        ]);

        ReportePagosPedidosProgreso::iniciar($jobId);

        GenerarReportePagosPedidosPdfJob::dispatch($filtros, $usuario, $jobId);

        return [
            'job_id' => $jobId,
            'exportacion' => $exportacion->fresh()->paraApi(),
        ];
    }
}
