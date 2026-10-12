<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportarDetallePreparacionTiendaService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        int $productoId,
        string $estadoFisico,
        string $observacion,
    ): PedidoBmaTareaProducto {
        if (! $usuario->can('control_pedidos.tienda.apartado.separar')
            && ! $usuario->can('control_pedidos.tienda.responder')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para reportar un detalle.',
            ]);
        }

        $tarea->loadMissing('modalidad');
        if ($tarea->modalidad?->codigo !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
            throw ValidationException::withMessages([
                'modalidad' => 'Reportar detalle en este paso solo aplica a Recoge en tienda.',
            ]);
        }

        if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
            throw ValidationException::withMessages([
                'estado' => 'La tarea debe estar en atención para reportar un detalle.',
            ]);
        }

        if (! in_array($estadoFisico, PedidoBmaRevisionProducto::ESTADOS, true)) {
            throw ValidationException::withMessages([
                'estado_fisico' => 'Seleccione un estado físico válido.',
            ]);
        }

        $observacion = trim($observacion);
        if ($observacion === '') {
            throw ValidationException::withMessages([
                'observacion' => 'Describa el detalle.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $productoId, $estadoFisico, $observacion) {
            $this->asegurar->ejecutar($tarea);
            $producto = PedidoBmaTareaProducto::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->where('id', $productoId)
                ->first();
            if (! $producto) {
                throw ValidationException::withMessages([
                    'producto' => 'La pieza no pertenece a esta tarea.',
                ]);
            }

            $producto->update([
                'estado_fisico' => $estadoFisico,
                'observacion' => $observacion,
            ]);

            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->first();
            if ($cumplimiento && $cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO) {
                $this->eventos->ejecutar(
                    $cumplimiento,
                    PedidoBmaCumplimientoEvento::TIPO_DETALLE_REPORTADO,
                    $usuario,
                    'detalle:'.$producto->id.':'.$cumplimiento->version,
                    $observacion,
                    ['estado_fisico' => $estadoFisico, 'sku' => $producto->sku]
                );
            }

            return $producto->fresh();
        });
    }
}
