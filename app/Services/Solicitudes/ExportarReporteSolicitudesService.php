<?php

namespace App\Services\Solicitudes;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarReporteSolicitudesService
{
    public function __construct(
        private ListarSolicitudesService $listarSolicitudes,
        private ResolverResolucionSolicitudService $resolverResolucion,
    ) {}

    public function obtenerSolicitudes(?User $usuario, array $filtros): Collection
    {
        return $this->listarSolicitudes->ejecutar($usuario, $filtros, false);
    }

    public function contar(?User $usuario, array $filtros): int
    {
        return $this->obtenerSolicitudes($usuario, $filtros)->count();
    }

    public function filas(Collection $solicitudes): array
    {
        return $solicitudes->map(function ($solicitud) {
            $resolucion = $this->resolverResolucion->resolver($solicitud);

            return [
                'Nombre de la vendedora' => $solicitud->vendedor->name ?? 'N/A',
                'Fecha de emisión' => $solicitud->created_at?->format('Y-m-d H:i') ?? '',
                'Número de cliente' => $solicitud->cliente->numero_cliente ?? 'N/A',
                'Nombre del cliente' => $solicitud->cliente->nombre ?? 'N/A',
                'Tipo de cliente' => $solicitud->tipoCliente->nombre ?? 'Normal',
                'Proceso' => $solicitud->proceso->nombre ?? 'N/A',
                'Respuesta / resolución' => $resolucion['respuesta'],
                'Fecha de resolución final' => $resolucion['fecha_resolucion']?->format('Y-m-d H:i') ?? '',
            ];
        })->all();
    }

    public function descargarExcel(?User $usuario, array $filtros): BinaryFileResponse|StreamedResponse
    {
        $solicitudes = $this->obtenerSolicitudes($usuario, $filtros);
        $nombreArchivo = 'reporte_solicitudes_' . now()->format('Y-m-d_His') . '.xlsx';

        return (new FastExcel($this->filas($solicitudes)))->download($nombreArchivo);
    }

    public function descargarCsv(?User $usuario, array $filtros): BinaryFileResponse|StreamedResponse
    {
        $solicitudes = $this->obtenerSolicitudes($usuario, $filtros);
        $nombreArchivo = 'reporte_solicitudes_' . now()->format('Y-m-d_His') . '.csv';

        return (new FastExcel($this->filas($solicitudes)))->download($nombreArchivo);
    }

    public function descargarPdf(?User $usuario, array $filtros)
    {
        $solicitudes = $this->obtenerSolicitudes($usuario, $filtros);
        $filas = $this->filas($solicitudes);

        $pdf = Pdf::loadView('reportes.solicitudes', [
            'titulo' => 'Reporte de Solicitudes Financieras',
            'fecha' => now()->format('Y-m-d H:i'),
            'total' => count($filas),
            'filas' => $filas,
        ])->setPaper('a4', 'landscape');

        $nombreArchivo = 'reporte_solicitudes_' . now()->format('Y-m-d_His') . '.pdf';

        return $pdf->download($nombreArchivo);
    }
}
