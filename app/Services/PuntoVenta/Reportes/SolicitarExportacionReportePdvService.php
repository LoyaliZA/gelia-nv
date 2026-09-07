<?php

namespace App\Services\PuntoVenta\Reportes;

use App\Jobs\GenerarExportacionReportePdvJob;
use App\Models\PuntoVenta\ReportePdvExportacion;
use App\Models\User;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use Illuminate\Support\Str;

class SolicitarExportacionReportePdvService
{
    public function __construct(
        private readonly EstimarExportacionReportePdvService $estimar,
        private readonly GenerarCsvExportacionReportePdvService $generarCsv,
        private readonly GenerarPdfExportacionReportePdvService $generarPdf,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{modo: string, job_id: string, exportacion: array<string, mixed>}
     */
    public function ejecutar(User $usuario, array $filtros): array
    {
        $formato = (string) ($filtros['formato'] ?? ReportePdvExportacionFormato::CSV);
        $tipo = ReportePdvExportacionTipo::desdeFiltros($filtros);
        $filtros['formato'] = $formato;
        $filtros['tipo_reporte'] = $tipo;

        $jobId = Str::uuid()->toString();
        $pesado = $this->estimar->esPesado($filtros);
        $expiraHoras = (int) config('punto_venta.reportes.exportacion.expira_horas', 48);

        $exportacion = ReportePdvExportacion::query()->create([
            'id' => $jobId,
            'user_id' => $usuario->id,
            'titulo' => TituloExportacionReportePdvService::desdeFiltros($filtros),
            'tipo_reporte' => $tipo,
            'formato' => $formato,
            'alcance' => $this->alcanceEtiqueta($filtros),
            'estado' => ReportePdvExportacion::ESTADO_PENDING,
            'filtros' => $filtros,
            'expira_at' => now()->addHours($expiraHoras),
        ]);

        if ($pesado) {
            GenerarExportacionReportePdvJob::dispatch($filtros, $usuario, $jobId);

            return [
                'modo' => 'asincrono',
                'job_id' => $jobId,
                'exportacion' => $exportacion->fresh()->paraApi(),
            ];
        }

        $this->completarExportacion($exportacion, $usuario, $filtros);

        return [
            'modo' => 'sincrono',
            'job_id' => $jobId,
            'exportacion' => $exportacion->fresh()->paraApi(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{modo: string, job_id: string, exportacion: array<string, mixed>}
     */
    public function reintentar(ReportePdvExportacion $exportacion, User $usuario): array
    {
        if ((int) $exportacion->user_id !== (int) $usuario->id) {
            abort(404);
        }

        if (! $exportacion->puedeReintentar()) {
            abort(422, 'La exportación no admite reintento.');
        }

        $filtros = $exportacion->filtros ?? [];
        $filtros['formato'] = $exportacion->formato;
        $filtros['tipo_reporte'] = $exportacion->tipo_reporte;

        $exportacion->update([
            'estado' => ReportePdvExportacion::ESTADO_PENDING,
            'error' => null,
            'nombre_archivo' => null,
            'ruta_archivo' => null,
            'tamano_bytes' => null,
            'num_registros' => null,
            'started_at' => null,
            'completed_at' => null,
            'expira_at' => now()->addHours((int) config('punto_venta.reportes.exportacion.expira_horas', 48)),
        ]);

        GenerarExportacionReportePdvJob::dispatch($filtros, $usuario, $exportacion->id);

        return [
            'modo' => 'asincrono',
            'job_id' => $exportacion->id,
            'exportacion' => $exportacion->fresh()->paraApi(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function completarExportacion(ReportePdvExportacion $exportacion, User $usuario, array $filtros): void
    {
        if ($exportacion->estado === ReportePdvExportacion::ESTADO_COMPLETED && ! $exportacion->estaExpirado()) {
            return;
        }

        $exportacion->update([
            'estado' => ReportePdvExportacion::ESTADO_PROCESSING,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $resultado = $this->generar($usuario, $filtros, $exportacion->id);

            $exportacion->update([
                'estado' => ReportePdvExportacion::ESTADO_COMPLETED,
                'nombre_archivo' => $resultado['nombre_archivo'],
                'ruta_archivo' => $resultado['path'],
                'tamano_bytes' => $resultado['tamano_bytes'],
                'num_registros' => $resultado['num_registros'],
                'completed_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $exportacion->update([
                'estado' => ReportePdvExportacion::ESTADO_FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_registros: int}
     */
    private function generar(User $usuario, array $filtros, string $exportacionId): array
    {
        $formato = (string) ($filtros['formato'] ?? ReportePdvExportacionFormato::CSV);

        if ($formato === ReportePdvExportacionFormato::PDF) {
            return $this->generarPdf->ejecutar($usuario, $filtros, $exportacionId);
        }

        return $this->generarCsv->ejecutar($usuario, $filtros, $exportacionId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function alcanceEtiqueta(array $filtros): ?string
    {
        $alcance = $filtros['alcance'] ?? null;
        if ($alcance === null || $alcance === '') {
            return null;
        }

        return (string) $alcance;
    }
}
