<?php

namespace App\Services\WooCommerce;

use App\Models\Woocommerce\WoocommerceSyncLog;
use App\Models\Woocommerce\WoocommerceTemplate;
use Illuminate\Support\Facades\Storage;

class WooCommerceExportacionCsvService
{
    public function __construct(
        private WooCommercePreciosService $preciosService
    ) {}

    public function ejecutar(WoocommerceSyncLog $log): void
    {
        $payload = $log->payload ?? [];
        $filePath = (string) ($payload['file_path'] ?? '');
        $mapping = $payload['mapping'] ?? null;
        $columnasExport = $payload['columnas_export'] ?? null;

        if ($filePath === '' || ! is_array($mapping)) {
            throw new \RuntimeException('Datos incompletos para generar el CSV.');
        }

        $fullPath = Storage::path($filePath);
        if (! file_exists($fullPath)) {
            throw new \RuntimeException('Archivo temporal no encontrado. Vuelve a subir el Excel.');
        }

        $log->update([
            'estado' => 'en_proceso',
            'payload' => array_merge($payload, ['fase' => 'leyendo_excel']),
        ]);

        $columnasExport = $this->preciosService->normalizarColumnasExport($columnasExport);
        $preciosWizerp = $this->preciosService->extraerPreciosDesdeExcel($fullPath, $mapping);

        $totalCatalogo = \App\Models\Woocommerce\WoocommerceProduct::count();
        $log->update([
            'total_productos' => max(1, $totalCatalogo),
            'procesados' => 0,
            'payload' => array_merge($log->fresh()->payload ?? [], ['fase' => 'analisis']),
        ]);

        $cambios = $this->preciosService->generarAnalisisDeCambios(
            $preciosWizerp,
            null,
            null,
            function (int $procesados, int $total) use ($log): void {
                $log->update([
                    'procesados' => $procesados,
                    'total_productos' => max(1, $total),
                ]);
            }
        );

        if ($cambios === []) {
            $this->limpiarArchivoTemporal($filePath);
            $log->update([
                'estado' => 'error',
                'mensaje_error' => 'No hay cambios de precios para exportar. Los precios locales ya coinciden con el listado.',
                'payload' => array_merge($log->payload ?? [], ['fase' => 'sin_cambios']),
            ]);

            return;
        }

        $totalCambios = count($cambios);
        $log->update([
            'total_productos' => $totalCambios,
            'procesados' => 0,
            'payload' => array_merge($log->payload ?? [], [
                'fase' => 'aplicando',
                'cambios_detectados' => $totalCambios,
            ]),
        ]);

        $productosActualizados = $this->preciosService->aplicarPreciosLocales(
            $cambios,
            function (int $procesados, int $total) use ($log): void {
                $log->update(['procesados' => $procesados, 'total_productos' => $total]);
            }
        );

        $log->update([
            'procesados' => $totalCambios,
            'payload' => array_merge($log->fresh()->payload ?? [], ['fase' => 'generando_csv']),
        ]);

        $fileName = 'WOOCOMMERCE-SYNC-' . date('d-m-Y_H-i-s') . '.csv';
        $ruta = 'woocommerce/' . $fileName;

        $tempPath = tempnam(sys_get_temp_dir(), 'woo');
        $csvMeta = $this->preciosService->escribirCsvExportacion($tempPath, $cambios, $columnasExport);

        $contenidoCsv = file_get_contents($tempPath);
        if ($contenidoCsv === false || $contenidoCsv === '') {
            throw new \RuntimeException('El CSV temporal quedó vacío.');
        }

        $guardado = Storage::disk('public')->put($ruta, $contenidoCsv);
        if ($guardado === false) {
            throw new \RuntimeException('No se pudo guardar el CSV en el disco public (revisa permisos de storage).');
        }

        $sizeKb = round($csvMeta['tamano_bytes'] / 1024, 2);
        unlink($tempPath);

        $template = WoocommerceTemplate::create([
            'nombre_archivo' => $fileName,
            'ruta_fisica' => $ruta,
            'tamano_kb' => $sizeKb . ' KB',
        ]);

        $this->limpiarArchivoTemporal($filePath);

        $mensaje = sprintf(
            'CSV con %d producto(s) con cambio de precio. Se actualizaron %d registro(s) en GELIANV.',
            $totalCambios,
            $productosActualizados
        );

        $log->update([
            'estado' => 'completado',
            'procesados' => $totalCambios,
            'total_productos' => $totalCambios,
            'mensaje_error' => null,
            'payload' => array_merge($log->payload ?? [], [
                'fase' => 'completado',
                'resultado' => [
                    'template_id' => $template->id,
                    'download_url' => route('woocommerce.descargar', $template->id),
                    'productos_exportados' => $totalCambios,
                    'productos_actualizados_local' => $productosActualizados,
                    'tamano_kb' => $sizeKb,
                    'nombre_archivo' => $fileName,
                    'message' => $mensaje,
                ],
            ]),
        ]);
    }

    private function limpiarArchivoTemporal(string $filePath): void
    {
        if (str_starts_with($filePath, 'temp/') && Storage::exists($filePath)) {
            Storage::delete($filePath);
        }
    }
}
