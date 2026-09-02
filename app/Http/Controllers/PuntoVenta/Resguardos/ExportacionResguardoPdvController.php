<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Resguardos\SolicitarExportacionResguardoPdvRequest;
use App\Models\PuntoVenta\ResguardoPdvExportacion;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\SolicitarExportacionResguardoPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionResguardoPdvController extends Controller
{
    public function store(
        SolicitarExportacionResguardoPdvRequest $request,
        SolicitarExportacionResguardoPdvService $solicitar,
    ): JsonResponse|StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $filtros = $request->filtros();

        Log::info('pdv.resguardos.exportacion.solicitud', [
            'usuario_id' => $user->id,
            'tipo' => $filtros['tipo'] ?? null,
            'filtros' => $filtros,
        ]);

        $resultado = $solicitar->ejecutar($user, $filtros);

        if ($resultado['modo'] === 'sincrono') {
            $exportacion = ResguardoPdvExportacion::query()
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

        if ($modelo->estado !== ResguardoPdvExportacion::ESTADO_COMPLETED || $modelo->estaExpirado()) {
            abort(404, 'Exportación no disponible.');
        }

        if (! $modelo->ruta_archivo || ! Storage::disk('local')->exists($modelo->ruta_archivo)) {
            abort(404);
        }

        Log::info('pdv.resguardos.exportacion.descarga', [
            'usuario_id' => auth()->id(),
            'exportacion_id' => $exportacion,
        ]);

        return Storage::disk('local')->download(
            $modelo->ruta_archivo,
            $modelo->nombre_archivo ?: ('resguardos_'.now()->format('Ymd_His').'.csv')
        );
    }

    private function exportacionAutorizada(string $exportacion): ResguardoPdvExportacion
    {
        return ResguardoPdvExportacion::query()
            ->where('user_id', auth()->id())
            ->findOrFail($exportacion);
    }

    private function respuestaDescarga(ResguardoPdvExportacion $modelo): StreamedResponse
    {
        if ($modelo->estado !== ResguardoPdvExportacion::ESTADO_COMPLETED || $modelo->estaExpirado()) {
            abort(500, 'La exportación síncrona no se completó.');
        }

        if (! $modelo->ruta_archivo || ! Storage::disk('local')->exists($modelo->ruta_archivo)) {
            abort(500, 'Archivo de exportación no encontrado.');
        }

        return Storage::disk('local')->download(
            $modelo->ruta_archivo,
            $modelo->nombre_archivo ?: ('resguardos_'.now()->format('Ymd_His').'.csv')
        );
    }
}
