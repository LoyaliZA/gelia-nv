<?php

namespace App\Console\Commands;

use App\Models\AuditoriaSolicitud;
use App\Models\AuditoriaSolicitudFactura;
use App\Models\CatalogoProceso;
use App\Models\SolicitudFactura;
use App\Models\SolicitudFacturaVoucher;
use App\Models\SolicitudTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrarFacturasLegacyCommand extends Command
{
    protected $signature = 'facturas:migrar-legacy {--dry-run : Simular sin escribir}';

    protected $description = 'Migra solicitudes SOLICITUD DE FACTURAS al módulo independiente de facturas';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $procesoFactura = CatalogoProceso::where('nombre', 'SOLICITUD DE FACTURAS')->first();

        if (!$procesoFactura) {
            $this->warn('No se encontró el proceso SOLICITUD DE FACTURAS. Nada que migrar.');

            return self::SUCCESS;
        }

        $solicitudes = SolicitudTag::withTrashed()
            ->with(['remisionesFactura'])
            ->where('catalogo_proceso_id', $procesoFactura->id)
            ->get();

        $migradas = 0;
        $omitidas = 0;

        if ($solicitudes->isNotEmpty()) {
            $this->info("Encontradas {$solicitudes->count()} solicitudes legacy.");

            foreach ($solicitudes as $solicitud) {
                if (SolicitudFactura::withTrashed()->where('legacy_solicitud_id', $solicitud->id)->exists()) {
                    $this->line("  Omitida solicitud #{$solicitud->id} (ya migrada).");
                    $omitidas++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [dry-run] Migraría solicitud #{$solicitud->id} → {$solicitud->factura_razon_social}");
                    $migradas++;
                    continue;
                }

                DB::transaction(function () use ($solicitud, &$migradas) {
                    $factura = SolicitudFactura::create([
                        'folio' => SolicitudFactura::generarFolio(),
                        'vendedor_id' => $solicitud->vendedor_id,
                        'departamento_id' => $solicitud->departamento_id,
                        'cliente_id' => $solicitud->cliente_id,
                        'catalogo_estado_solicitud_id' => $solicitud->catalogo_estado_solicitud_id,
                        'razon_social' => $solicitud->factura_razon_social ?? 'Sin razón social',
                        'datos_fiscales' => $solicitud->factura_datos_fiscales,
                        'archivo_fiscal_path' => $solicitud->archivo_facturas_path,
                        'observaciones_vendedor' => $solicitud->observaciones_vendedor,
                        'legacy_solicitud_id' => $solicitud->id,
                        'created_at' => $solicitud->created_at,
                        'updated_at' => $solicitud->updated_at,
                    ]);

                    foreach ($solicitud->remisionesFactura as $remision) {
                        SolicitudFacturaVoucher::create([
                            'solicitud_factura_id' => $factura->id,
                            'path' => $remision->path,
                            'nombre_original' => $remision->nombre_original,
                            'mime' => 'application/pdf',
                            'orden' => $remision->orden,
                        ]);
                    }

                    AuditoriaSolicitudFactura::create([
                        'solicitud_factura_id' => $factura->id,
                        'usuario_id' => $solicitud->vendedor_id,
                        'estado_anterior_id' => null,
                        'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                        'motivo_reporte' => "Migrado desde solicitud legacy #{$solicitud->id}",
                        'created_at' => $solicitud->created_at,
                        'updated_at' => $solicitud->updated_at,
                    ]);

                    foreach (AuditoriaSolicitud::where('solicitud_id', $solicitud->id)->get() as $aud) {
                        AuditoriaSolicitudFactura::create([
                            'solicitud_factura_id' => $factura->id,
                            'usuario_id' => $aud->usuario_id,
                            'estado_anterior_id' => $aud->estado_anterior_id,
                            'estado_nuevo_id' => $aud->estado_nuevo_id,
                            'motivo_reporte' => $aud->motivo_reporte,
                            'datos_snapshot' => $aud->datos_snapshot,
                            'created_at' => $aud->created_at,
                            'updated_at' => $aud->updated_at,
                        ]);
                    }

                    if (!$solicitud->trashed()) {
                        $solicitud->delete();
                    }

                    $migradas++;
                });

                $this->line("  Migrada solicitud #{$solicitud->id}");
            }
        } else {
            $this->info('No hay solicitudes de factura legacy pendientes.');
        }

        if (!$dryRun) {
            $this->desactivarProcesoFactura($procesoFactura);
        }

        if ($dryRun) {
            $this->info("Simulación completada: {$migradas} por migrar, {$omitidas} ya migradas.");
        } else {
            $this->info("Migración completada: {$migradas} nuevas, {$omitidas} omitidas (ya existían).");
        }

        return self::SUCCESS;
    }

    /**
     * Desactiva el proceso en lugar de eliminarlo: las solicitudes legacy soft-deleted
     * mantienen FK hacia catalogo_procesos (RESTRICT).
     */
    private function desactivarProcesoFactura(CatalogoProceso $proceso): void
    {
        if (!$proceso->activo) {
            $this->info('Proceso SOLICITUD DE FACTURAS ya estaba inactivo.');

            return;
        }

        $proceso->update(['activo' => false]);
        $this->info('Proceso SOLICITUD DE FACTURAS desactivado (ya no aparece en Solicitudes).');
    }
}
