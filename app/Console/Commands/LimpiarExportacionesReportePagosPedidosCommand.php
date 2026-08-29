<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reportes\ReportePagosPedidosExportacion;
use Illuminate\Support\Facades\Storage;

class LimpiarExportacionesReportePagosPedidosCommand extends Command
{
    protected $signature = 'reportes:limpiar-exportaciones-pagos-pedidos {--horas=48 : Antigüedad mínima en horas}';

    protected $description = 'Elimina PDFs temporales del reporte pagos pedidos más antiguos que N horas';

    public function handle(): int
    {
        $horas = max(1, (int) $this->option('horas'));
        $limite = now()->subHours($horas)->getTimestamp();
        $dir = storage_path('app/reportes_pagos_pedidos');

        if (! is_dir($dir)) {
            $this->info('Sin directorio de exportaciones.');

            return self::SUCCESS;
        }

        $eliminados = 0;
        foreach (glob($dir.'/*') ?: [] as $archivo) {
            if (is_file($archivo) && filemtime($archivo) < $limite) {
                @unlink($archivo);
                $eliminados++;
            }
        }

        $diskPath = 'reportes_pagos_pedidos';
        if (Storage::disk('local')->exists($diskPath)) {
            foreach (Storage::disk('local')->files($diskPath) as $file) {
                $mtime = Storage::disk('local')->lastModified($file);
                if ($mtime < $limite) {
                    Storage::disk('local')->delete($file);
                    $eliminados++;
                }
            }
        }

        $this->info("Exportaciones eliminadas: {$eliminados}");

        ReportePagosPedidosExportacion::query()
            ->where('estado', ReportePagosPedidosExportacion::ESTADO_COMPLETED)
            ->where('expira_at', '<', now())
            ->update(['estado' => ReportePagosPedidosExportacion::ESTADO_EXPIRED]);

        return self::SUCCESS;
    }
}
