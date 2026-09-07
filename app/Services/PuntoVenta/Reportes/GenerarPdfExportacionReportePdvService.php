<?php

namespace App\Services\PuntoVenta\Reportes;

use App\Models\User;
use App\Support\PuntoVenta\Reportes\ColumnasExportacionReportePdv;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerarPdfExportacionReportePdvService
{
    public function __construct(
        private readonly ObtenerPayloadMetricasReportePdvService $payloads,
        private readonly FilasExportacionReportePdvService $filas,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_registros: int}
     */
    public function ejecutar(User $usuario, array $filtros, string $exportacionId): array
    {
        $tipo = ReportePdvExportacionTipo::desdeFiltros($filtros);
        $bloques = $this->payloads->ejecutar($usuario, $filtros);
        $filasParametros = $this->filas->filasParametros($bloques, $tipo);
        $filasMetricas = $this->filas->filasMetricas($bloques);

        $secciones = [];
        foreach (ColumnasExportacionReportePdv::seccionesPdf() as $seccion) {
            if ($seccion === 'parametros') {
                $secciones[$seccion] = $filasParametros;

                continue;
            }

            $secciones[$seccion] = array_values(array_filter(
                $filasMetricas,
                static fn (array $fila) => ($fila['seccion'] ?? '') === $seccion
            ));
        }

        $pdf = Pdf::loadView('reportes.pdv_metricas_pdf', [
            'titulo' => TituloExportacionReportePdvService::desdeFiltros($filtros),
            'tipo_reporte' => $tipo,
            'solicitante' => $usuario->name,
            'generado_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'secciones' => $secciones,
            'columnas_metricas' => $this->filas->columnasMetricas(),
            'columnas_parametros' => $this->filas->columnasParametros(),
        ])->setPaper('a4', 'portrait');

        $nombre = 'reporte_pdv_'.$tipo.'_'.$exportacionId.'.pdf';
        $rutaRelativa = 'pdv/reportes/exportaciones/'.$exportacionId.'.pdf';
        $disco = Storage::disk('local');
        $disco->makeDirectory('pdv/reportes/exportaciones');
        $disco->put($rutaRelativa, $pdf->output());

        return [
            'path' => $rutaRelativa,
            'nombre_archivo' => $nombre,
            'tamano_bytes' => (int) $disco->size($rutaRelativa),
            'num_registros' => count($filasMetricas),
        ];
    }
}
