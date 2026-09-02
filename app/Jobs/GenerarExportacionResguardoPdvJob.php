<?php

namespace App\Jobs;

use App\Models\PuntoVenta\ResguardoPdvExportacion;
use App\Models\User;
use App\Notifications\ResguardoPdvExportacionNotification;
use App\Services\PuntoVenta\Resguardos\SolicitarExportacionResguardoPdvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerarExportacionResguardoPdvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        public array $filtros,
        public User $usuarioSolicitante,
        public string $jobId,
    ) {}

    public function handle(SolicitarExportacionResguardoPdvService $solicitar): void
    {
        $exportacion = ResguardoPdvExportacion::query()->find($this->jobId);
        if (! $exportacion) {
            return;
        }

        try {
            $solicitar->completarExportacion($exportacion, $this->usuarioSolicitante, $this->filtros);
            $this->usuarioSolicitante->notify(
                new ResguardoPdvExportacionNotification($exportacion->fresh(), true)
            );
        } catch (Throwable $e) {
            $this->usuarioSolicitante->notify(
                new ResguardoPdvExportacionNotification($exportacion->fresh(), false)
            );

            throw $e;
        }
    }
}
