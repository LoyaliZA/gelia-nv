<?php

namespace App\Services\PuntoVenta\Reportes;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class GenerarCsvExportacionReportePdvService
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
        $nombre = 'reporte_pdv_'.$tipo.'_'.$exportacionId.'.csv';
        $rutaRelativa = 'pdv/reportes/exportaciones/'.$exportacionId.'.csv';
        $disco = Storage::disk('local');
        $disco->makeDirectory('pdv/reportes/exportaciones');

        $rutaAbsoluta = $disco->path($rutaRelativa);
        $out = fopen($rutaAbsoluta, 'w');
        if ($out === false) {
            throw new \RuntimeException('No se pudo crear el archivo de exportación.');
        }

        fwrite($out, "\xEF\xBB\xBF");
        $this->escribirSeccion($out, 'Parámetros', $this->filas->columnasParametros(), $filasParametros);
        $this->escribirSeccion($out, 'Métricas', $this->filas->columnasMetricas(), $filasMetricas);
        fclose($out);

        $totalFilas = count($filasParametros) + count($filasMetricas);

        return [
            'path' => $rutaRelativa,
            'nombre_archivo' => $nombre,
            'tamano_bytes' => (int) filesize($rutaAbsoluta),
            'num_registros' => $totalFilas,
        ];
    }

    /**
     * @param  resource  $out
     * @param  array<string, string>  $columnas
     * @param  list<array<string, mixed>>  $filas
     */
    private function escribirSeccion($out, string $titulo, array $columnas, array $filas): void
    {
        fputcsv($out, ['### '.$titulo]);
        fputcsv($out, array_values($columnas));

        foreach ($filas as $fila) {
            $valores = [];
            foreach (array_keys($columnas) as $clave) {
                $valores[] = $fila[$clave] ?? '';
            }
            fputcsv($out, $valores);
        }

        fputcsv($out, []);
    }
}
