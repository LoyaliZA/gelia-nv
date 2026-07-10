<?php

namespace App\Jobs;

use App\Models\CobranzaBitacora;
use App\Models\CobranzaFactura;
use App\Models\Cliente;
use App\Models\User;
use App\Support\CobranzaReporteAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Throwable;

class GenerarReporteCobranzaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public array $filtros,
        public User $usuarioSolicitante,
        public string $jobId
    ) {}

    public function handle(): void
    {
        $this->updateProgress(10, 'processing');

        $facturasQuery = CobranzaFactura::with('cliente')->where('pagada', false);
        $abonosQuery = CobranzaBitacora::with('cliente')->where('tipo_evento', 'abono');
        $bitacoraQuery = CobranzaBitacora::with('cliente')->where('tipo_evento', '!=', 'abono');

        if (!empty($this->filtros['cliente_id'])) {
            $facturasQuery->where('cliente_id', $this->filtros['cliente_id']);
            $abonosQuery->where('cliente_id', $this->filtros['cliente_id']);
            $bitacoraQuery->where('cliente_id', $this->filtros['cliente_id']);
        }

        if (!empty($this->filtros['fecha_inicio'])) {
            $abonosQuery->whereDate('created_at', '>=', $this->filtros['fecha_inicio']);
            $bitacoraQuery->whereDate('created_at', '>=', $this->filtros['fecha_inicio']);
        }
        if (!empty($this->filtros['fecha_fin'])) {
            $abonosQuery->whereDate('created_at', '<=', $this->filtros['fecha_fin']);
            $bitacoraQuery->whereDate('created_at', '<=', $this->filtros['fecha_fin']);
        }

        $this->updateProgress(30, 'processing');

        $facturas = $facturasQuery->get();
        $abonos = $abonosQuery->get();
        $bitacora = $bitacoraQuery->get();

        $this->updateProgress(60, 'processing');

        $fileName = 'reporte_cobranza_' . now()->format('Ymd_His');
        $folder = 'reportes_cobranza';
        Storage::disk('public')->makeDirectory($folder);

        if ($this->filtros['formato'] === 'excel') {
            $fileName .= '.xlsx';
            $path = "$folder/$fileName";
            $absolutePath = storage_path("app/public/$path");

            $sheets = new SheetCollection([
                'Folios Pendientes' => $facturas->map(fn($f) => [
                    'Folio' => $f->folio,
                    'Cliente' => $f->cliente?->nombre,
                    'Monto Pendiente' => $f->monto,
                    'Fecha Vencimiento' => $f->fecha_vencimiento?->format('Y-m-d'),
                ]),
                'Abonos' => $abonos->map(fn($a) => [
                    'Cliente' => $a->cliente?->nombre,
                    'Monto Abono' => $a->monto_anterior - $a->monto_nuevo,
                    'Monto Anterior' => $a->monto_anterior,
                    'Nuevo Saldo' => $a->monto_nuevo,
                    'Fecha' => $a->created_at->format('Y-m-d H:i:s'),
                ]),
                'Bitacora' => $bitacora->map(fn($b) => [
                    'Cliente' => $b->cliente?->nombre,
                    'Evento' => $b->tipo_evento,
                    'Descripcion' => $b->descripcion,
                    'Fecha' => $b->created_at->format('Y-m-d H:i:s'),
                ])
            ]);

            (new FastExcel($sheets))->export($absolutePath);
        } else {
            $fileName .= '.pdf';
            $path = "$folder/$fileName";

            $this->updateProgress(70, 'processing');

            try {
                $graficaDeuda = CobranzaReporteAssets::datosGraficaDeuda($facturas);
                $clienteFiltro = !empty($this->filtros['cliente_id'])
                    ? Cliente::find($this->filtros['cliente_id'])
                    : null;

                $totalAbonos = $abonos->sum(fn ($a) => $a->monto_anterior - $a->monto_nuevo);
                $foliosVencidos = $facturas->filter(
                    fn ($f) => $f->fecha_vencimiento && $f->fecha_vencimiento->isPast()
                )->count();

                $logos = CobranzaReporteAssets::logosEmpresa();

                $this->updateProgress(85, 'processing');

                $pdf = Pdf::loadView('reportes.cobranza_pdf', [
                    'facturas' => $facturas,
                    'abonos' => $abonos,
                    'bitacora' => $bitacora,
                    'logos' => $logos,
                    'graficaDeuda' => $graficaDeuda,
                    'filtrosResumen' => [
                        'cliente' => $clienteFiltro
                            ? trim("{$clienteFiltro->numero_cliente} — {$clienteFiltro->nombre}")
                            : 'Todos los clientes',
                        'periodo' => $this->formatearPeriodoFiltros(),
                    ],
                    'resumen' => [
                        'deuda_total' => (float) $facturas->sum('monto'),
                        'total_abonos' => (float) $totalAbonos,
                        'clientes_con_deuda' => $facturas->pluck('cliente_id')->unique()->filter()->count(),
                        'folios_vencidos' => $foliosVencidos,
                    ],
                    'fechaGeneracion' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    'usuarioNombre' => $this->usuarioSolicitante->name,
                ])->setPaper('a4', 'portrait');

                Storage::disk('public')->put($path, $pdf->output());
            } catch (Throwable $e) {
                $this->marcarError($e->getMessage());
                throw $e;
            }
        }

        $this->updateProgress(100, 'completed', $path);
    }

    public function failed(?Throwable $exception): void
    {
        $this->marcarError($exception?->getMessage() ?? 'Error desconocido al generar el reporte.');
    }

    private function marcarError(string $mensaje): void
    {
        Cache::put("reporte_cobranza_{$this->jobId}", [
            'progress' => 0,
            'status' => 'error',
            'error' => $mensaje,
            'file_path' => null,
        ], now()->addHours(2));
    }

    private function updateProgress($percentage, $status, $filePath = null)
    {
        Cache::put("reporte_cobranza_{$this->jobId}", [
            'progress' => $percentage,
            'status' => $status,
            'file_path' => $filePath,
        ], now()->addHours(2));
    }

    private function formatearPeriodoFiltros(): string
    {
        $inicio = $this->filtros['fecha_inicio'] ?? null;
        $fin = $this->filtros['fecha_fin'] ?? null;

        if ($inicio && $fin) {
            return "{$inicio} al {$fin}";
        }

        if ($inicio) {
            return "Desde {$inicio}";
        }

        if ($fin) {
            return "Hasta {$fin}";
        }

        return 'Sin restricción de fechas';
    }
}
