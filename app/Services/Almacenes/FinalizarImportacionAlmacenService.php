<?php

namespace App\Services\Almacenes;

use Illuminate\Http\RedirectResponse;

class FinalizarImportacionAlmacenService
{
    public function __construct(
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function respuesta(
        RedirectResponse $redirect,
        string $tipoAuditoria,
        array $stats,
        ReporteErroresImportacionService $reporte,
    ): RedirectResponse {
        $token = $reporte->generarCsv();
        $reporteUrl = $token
            ? route('almacenes.importaciones.reporte_errores', ['token' => $token])
            : null;

        $payload = array_merge($stats, [
            'errores_detalle' => $reporte->resumen(),
            'reporte_url' => $reporteUrl,
        ]);

        $this->auditoria->importacion($tipoAuditoria, $payload);

        $mensaje = sprintf(
            'Importación completada: %d nuevos, %d actualizados, %d omitidos.',
            $stats['importados'] ?? 0,
            $stats['actualizados'] ?? 0,
            $stats['omitidos'] ?? 0,
        );

        if ($reporte->tieneErrores()) {
            $mensaje .= ' Descarga el reporte de errores para revisar las filas omitidas.';
        }

        return $redirect
            ->with('success', $mensaje)
            ->with('reporte_importacion_almacenes', $payload);
    }
}
