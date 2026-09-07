<?php

namespace App\Jobs;

use App\Models\PuntoVenta\ReportePdvExportacion;
use App\Models\User;
use App\Notifications\ReportePdvExportacionNotification;
use App\Services\PuntoVenta\Reportes\SolicitarExportacionReportePdvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerarExportacionReportePdvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public $timeout = 300;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        public array $filtros,
        public User $usuarioSolicitante,
        public string $jobId,
    ) {}

    public function handle(SolicitarExportacionReportePdvService $solicitar): void
    {
        $exportacion = ReportePdvExportacion::query()->find($this->jobId);
        if (! $exportacion) {
            return;
        }

        if ($exportacion->estado === ReportePdvExportacion::ESTADO_COMPLETED && ! $exportacion->estaExpirado()) {
            return;
        }

        try {
            $solicitar->completarExportacion($exportacion, $this->usuarioSolicitante, $this->filtros);
            $this->usuarioSolicitante->notify(
                new ReportePdvExportacionNotification($exportacion->fresh(), true)
            );
        } catch (Throwable $e) {
            $this->usuarioSolicitante->notify(
                new ReportePdvExportacionNotification($exportacion->fresh(), false)
            );

            throw $e;
        }
    }
}
