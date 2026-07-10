<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Jobs\GenerarReporteCobranzaJob;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class ReporteCobranzaController extends Controller
{
    public function generar(Request $request)
    {
        Gate::authorize('cobranza.reportes');

        $validated = $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'formato' => 'required|in:pdf,excel',
        ]);

        $jobId = Str::uuid()->toString();

        // Initialize progress in Cache
        Cache::put("reporte_cobranza_{$jobId}", [
            'progress' => 0,
            'status' => 'processing',
            'file_url' => null,
        ], now()->addHours(2));

        // Dispatch the job
        GenerarReporteCobranzaJob::dispatch($validated, auth()->user(), $jobId);

        return response()->json([
            'message' => 'Reporte encolado.',
            'job_id' => $jobId,
        ]);
    }

    public function estado($jobId)
    {
        Gate::authorize('cobranza.reportes');

        $estado = Cache::get("reporte_cobranza_{$jobId}", [
            'progress' => 0,
            'status' => 'pending',
            'file_url' => null,
        ]);

        return response()->json($estado);
    }

    public function descargar($jobId)
    {
        Gate::authorize('cobranza.reportes');

        $estado = Cache::get("reporte_cobranza_{$jobId}");

        if (!$estado || $estado['status'] !== 'completed' || empty($estado['file_path'])) {
            abort(404, 'Reporte no encontrado o aún no generado.');
        }

        return response()->download(storage_path('app/public/' . $estado['file_path']));
    }
}
