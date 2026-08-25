<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportarIncidenciaPreparacionService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<int>  $productosAfectados
     * @param  list<UploadedFile>  $evidencias
     */
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $tipoIncidencia,
        string $motivo,
        int $almacenSolicitadoId,
        ?int $almacenAparenteId,
        array $productosAfectados,
        ?string $observacion = null,
        array $evidencias = [],
        ?int $versionEsperada = null,
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.tienda.reportar_error')) {
            throw new \RuntimeException('No tiene permiso para reportar incidencias.');
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'El motivo es obligatorio.']);
        }

        return DB::transaction(function () use (
            $tarea, $usuario, $tipoIncidencia, $motivo, $almacenSolicitadoId,
            $almacenAparenteId, $productosAfectados, $observacion, $evidencias, $versionEsperada
        ) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);

            if (! in_array($tarea->estado, [
                PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
                PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            ], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede reportar incidencia en el estado actual.',
                ]);
            }

            foreach ($evidencias as $archivo) {
                if ($archivo instanceof UploadedFile && $archivo->isValid()) {
                    $ruta = $archivo->store("pedidos_bma/tareas_preparacion/{$tarea->id}/incidencia", 'public');
                    $tarea->documentos()->create([
                        'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_INCIDENCIA,
                        'ruta_interna' => $ruta,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'mime_type' => $archivo->getMimeType(),
                        'tamano_bytes' => $archivo->getSize(),
                        'subido_por_id' => $usuario->id,
                        'subido_at' => now(),
                        'inmutable' => true,
                    ]);
                }
            }

            $meta = [
                'tipo_incidencia' => $tipoIncidencia,
                'motivo' => $motivo,
                'almacen_solicitado_id' => $almacenSolicitadoId,
                'almacen_aparente_id' => $almacenAparenteId,
                'productos_afectados' => $productosAfectados,
                'observacion' => $observacion,
            ];

            $tarea = $this->transicionService->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
                $usuario->id,
                'incidencia',
                "Incidencia: {$motivo}",
                $meta,
                $versionEsperada
            );

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor'])->first();
            $pedido->update(['consulta_actualizacion_pendiente' => true]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                "Tienda reportó incidencia: {$motivo}",
                AccionesHistorialPedidoBma::INCIDENCIA_PREPARACION_TIENDA
            );

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_preparacion_tienda_incidencia',
                "Tienda reportó una incidencia en la preparación: {$motivo}",
                [],
                $usuario->id,
                true,
                ['url' => '/control-pedidos?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $tarea->fresh(['modalidad', 'almacen', 'productos', 'historial']);
        });
    }
}
