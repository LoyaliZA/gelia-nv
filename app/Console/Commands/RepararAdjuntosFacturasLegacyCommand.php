<?php

namespace App\Console\Commands;

use App\Models\SolicitudFactura;
use App\Models\SolicitudFacturaVoucher;
use App\Models\SolicitudTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RepararAdjuntosFacturasLegacyCommand extends Command
{
    protected $signature = 'facturas:reparar-adjuntos {--dry-run : Simular sin escribir}';

    protected $description = 'Re-sincroniza Excel y vouchers desde solicitudes legacy hacia solicitudes_facturas';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $reparadas = 0;

        $facturas = SolicitudFactura::whereNotNull('legacy_solicitud_id')->with('vouchers')->get();

        foreach ($facturas as $factura) {
            $legacy = SolicitudTag::withTrashed()
                ->with('remisionesFactura')
                ->find($factura->legacy_solicitud_id);

            if (!$legacy) {
                $this->warn("  Legacy #{$factura->legacy_solicitud_id} no encontrada para {$factura->folio}");
                continue;
            }

            $cambios = [];

            if (!$factura->archivo_fiscal_path && $legacy->archivo_facturas_path) {
                if (Storage::disk('public')->exists($legacy->archivo_facturas_path)) {
                    $cambios[] = "excel: {$legacy->archivo_facturas_path}";
                    if (!$dryRun) {
                        $factura->archivo_fiscal_path = $legacy->archivo_facturas_path;
                    }
                }
            }

            if ($factura->vouchers->isEmpty() && $legacy->remisionesFactura->isNotEmpty()) {
                foreach ($legacy->remisionesFactura as $remision) {
                    if (!Storage::disk('public')->exists($remision->path)) {
                        $this->warn("  Archivo faltante en disco: {$remision->path}");
                        continue;
                    }
                    $cambios[] = "voucher: {$remision->path}";
                    if (!$dryRun) {
                        SolicitudFacturaVoucher::create([
                            'solicitud_factura_id' => $factura->id,
                            'path' => $remision->path,
                            'nombre_original' => $remision->nombre_original,
                            'mime' => 'application/pdf',
                            'orden' => $remision->orden,
                        ]);
                    }
                }
            }

            if (empty($cambios)) {
                continue;
            }

            if (!$dryRun && $factura->isDirty()) {
                $factura->save();
            }

            $this->line("  {$factura->folio}: " . implode(', ', $cambios));
            $reparadas++;
        }

        $this->info($dryRun
            ? "Simulación: {$reparadas} solicitud(es) reparables."
            : "Reparadas {$reparadas} solicitud(es).");

        return self::SUCCESS;
    }
}
