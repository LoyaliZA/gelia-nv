<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnexarPiezaPreparacionTiendaService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $sku,
        string $descripcion,
        int $cantidad,
    ): PedidoBmaTareaProducto {
        $this->assertRecogeHoyEnAtencion($tarea, $usuario);

        $sku = trim($sku);
        $descripcion = trim($descripcion);
        if ($sku === '' || $cantidad < 1) {
            throw ValidationException::withMessages([
                'sku' => 'Indique SKU y una cantidad mayor a cero.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $sku, $descripcion, $cantidad) {
            $this->asegurar->ejecutar($tarea);
            $orden = (int) $tarea->productos()->max('orden') + 1;
            $producto = PedidoBmaTareaProducto::query()->create([
                'pedido_bma_tarea_preparacion_id' => $tarea->id,
                'pedido_bma_origen_id' => $tarea->pedido_bma_id,
                'sku' => $sku,
                'descripcion_snapshot' => $descripcion !== '' ? $descripcion : $sku,
                'cantidad_solicitada' => $cantidad,
                'cantidad_encontrada' => $cantidad,
                'estado_fisico' => 'bueno',
                'orden' => $orden,
            ]);

            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->first();
            if ($cumplimiento) {
                $this->eventos->ejecutar(
                    $cumplimiento,
                    PedidoBmaCumplimientoEvento::TIPO_PIEZA_ANEXADA,
                    $usuario,
                    'anexar:'.$producto->id,
                    'Pieza anexada al apartado.',
                    ['sku' => $sku, 'cantidad' => $cantidad]
                );
            }

            return $producto;
        });
    }

    private function assertRecogeHoyEnAtencion(PedidoBmaTareaPreparacion $tarea, User $usuario): void
    {
        if (! $usuario->can('control_pedidos.tienda.apartado.separar')
            && ! $usuario->can('control_pedidos.tienda.responder')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para modificar este apartado.',
            ]);
        }

        $tarea->loadMissing('modalidad');
        if ($tarea->modalidad?->codigo !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
            throw ValidationException::withMessages([
                'modalidad' => 'Anexar piezas en este paso solo aplica a Recoge en tienda.',
            ]);
        }

        if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
            throw ValidationException::withMessages([
                'estado' => 'La tarea debe estar en atención para anexar piezas.',
            ]);
        }
    }
}
