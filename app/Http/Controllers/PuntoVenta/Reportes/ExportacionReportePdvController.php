<?php

namespace App\Http\Controllers\PuntoVenta\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Reportes\SolicitarExportacionReportePdvRequest;
use App\Models\PuntoVenta\ReportePdvExportacion;
use App\Models\User;
use App\Services\PuntoVenta\Reportes\SolicitarExportacionReportePdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionReportePdvController extends Controller
{
    public function store(
        SolicitarExportacionReportePdvRequest $request,
        SolicitarExportacionReportePdvService $solicitar,
    ): JsonResponse|StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $filtros = $request->filtros();

        Log::info('pdv.reportes.exportacion.solicitud', [
            'usuario_id' => $user->id,
            'tipo_reporte' => $filtros['tipo_reporte'] ?? null,
            'formato' => $filtros['formato'] ?? null,
            'filtros' => $filtros,
        ]);

        $resultado = $solicitar->ejecutar($user, $filtros);

        if ($resultado['modo'] === 'sincrono') {
            $exportacion = ReportePdvExportacion::query()
                ->where('user_id', $user->id)
                ->findOrFail($resultado['job_id']);

            return $this->respuestaDescarga($exportacion);
        }

        return response()->json([
            'modo' => $resultado['modo'],
            'job_id' => $resultado['job_id'],
            'exportacion' => $resultado['exportacion'],
            'message' => 'Generación en cola.',
        ], 202);
    }

    public function show(string $exportacion): JsonResponse
    {
        $modelo = $this->exportacionAutorizada($exportacion);

        return response()->json($modelo->paraApi());
    }

    public function descargar(string $exportacion): StreamedResponse
    {
        $modelo = $this->exportacionAutorizada($exportacion);

        if ($modelo->estado !== ReportePdvExportacion::ESTADO_COMPLETED || $modelo->estaExpirado()) {
            abort(404, 'Exportación no disponible.');
        }

        if (! $modelo->ruta_archivo || ! Storage::disk('local')->exists($modelo->ruta_archivo)) {
            abort(404);
        }

        Log::info('pdv.reportes.exportacion.descarga', [
            'usuario_id' => auth()->id(),
            'exportacion_id' => $exportacion,
        ]);

        return Storage::disk('local')->download(
            $modelo->ruta_archivo,
            $modelo->nombre_archivo ?: ('reporte_pdv_'.now()->format('Ymd_His').'.'.$modelo->formato)
        );
    }

    public function reintentar(
        string $exportacion,
        SolicitarExportacionReportePdvService $solicitar,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();
        $modelo = $this->exportacionAutorizada($exportacion);
        $resultado = $solicitar->reintentar($modelo, $user);

        return response()->json([
            'modo' => $resultado['modo'],
            'job_id' => $resultado['job_id'],
            'exportacion' => $resultado['exportacion'],
            'message' => 'Reintento en cola.',
        ], 202);
    }

    private function exportacionAutorizada(string $exportacion): ReportePdvExportacion
    {
        return ReportePdvExportacion::query()
            ->where('user_id', auth()->id())
            ->findOrFail($exportacion);
    }

    private function respuestaDescarga(ReportePdvExportacion $modelo): StreamedResponse
    {
        if ($modelo->estado !== ReportePdvExportacion::ESTADO_COMPLETED || $modelo->estaExpirado()) {
            abort(500, 'La exportación síncrona no se completó.');
        }

        if (! $modelo->ruta_archivo || ! Storage::disk('local')->exists($modelo->ruta_archivo)) {
            abort(500, 'Archivo de exportación no encontrado.');
        }

        return Storage::disk('local')->download(
            $modelo->ruta_archivo,
            $modelo->nombre_archivo ?: ('reporte_pdv_'.now()->format('Ymd_His').'.'.$modelo->formato)
        );
    }
}
