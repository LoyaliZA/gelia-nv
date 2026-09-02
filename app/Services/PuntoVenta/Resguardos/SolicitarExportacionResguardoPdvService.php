<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Jobs\GenerarExportacionResguardoPdvJob;
use App\Models\PuntoVenta\ResguardoPdvExportacion;
use App\Models\User;
use Illuminate\Support\Str;

class SolicitarExportacionResguardoPdvService
{
    public function __construct(
        private readonly EstimarExportacionResguardoPdvService $estimar,
        private readonly GenerarCsvExportacionResguardoPdvService $generar,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{modo: string, job_id: string, exportacion: array<string, mixed>}
     */
    public function ejecutar(User $usuario, array $filtros): array
    {
        $tipo = ResguardoPdvExportacionTipo::desdeFiltros($filtros);
        $filtros['tipo'] = $tipo;
        $jobId = Str::uuid()->toString();
        $total = $this->estimar->contarRegistros($usuario, $filtros);
        $pesado = $this->estimar->esPesado($total);
        $expiraHoras = (int) config('punto_venta.resguardos.exportacion.expira_horas', 48);

        $exportacion = ResguardoPdvExportacion::query()->create([
            'id' => $jobId,
            'user_id' => $usuario->id,
            'resguardo_id' => $tipo === ResguardoPdvExportacionTipo::AUDITORIA
                ? (int) ($filtros['resguardo_id'] ?? null)
                : null,
            'titulo' => $this->estimar->titulo($usuario, $filtros),
            'tipo' => ResguardoPdvExportacionTipo::etiquetaModelo($tipo),
            'estado' => ResguardoPdvExportacion::ESTADO_PENDING,
            'filtros' => $filtros,
            'num_registros' => $total,
            'expira_at' => now()->addHours($expiraHoras),
        ]);

        if ($pesado) {
            GenerarExportacionResguardoPdvJob::dispatch($filtros, $usuario, $jobId);

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
     */
    public function completarExportacion(ResguardoPdvExportacion $exportacion, User $usuario, array $filtros): void
    {
        $exportacion->update([
            'estado' => ResguardoPdvExportacion::ESTADO_PROCESSING,
            'started_at' => now(),
        ]);

        try {
            $resultado = $this->generar->ejecutar($usuario, $filtros, $exportacion->id);

            $exportacion->update([
                'estado' => ResguardoPdvExportacion::ESTADO_COMPLETED,
                'nombre_archivo' => $resultado['nombre_archivo'],
                'ruta_archivo' => $resultado['path'],
                'tamano_bytes' => $resultado['tamano_bytes'],
                'num_registros' => $resultado['num_registros'],
                'resguardo_id' => $resultado['resguardo_id'] ?? $exportacion->resguardo_id,
                'completed_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $exportacion->update([
                'estado' => ResguardoPdvExportacion::ESTADO_FAILED,
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
