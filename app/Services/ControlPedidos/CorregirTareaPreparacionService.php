<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorregirTareaPreparacionService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<array{descripcion_snapshot: string, sku?: string|null, producto_id?: int|null, cantidad_solicitada?: int}>  $productos
     */
    public function ejecutar(
        PedidoBmaTareaPreparacion $tareaAnterior,
        User $usuario,
        int $almacenId,
        array $productos,
        ?string $observaciones = null,
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.preparacion.corregir')) {
            throw new \RuntimeException('No tiene permiso para corregir solicitudes de preparación.');
        }

        $tareaAnterior->loadMissing(['pedido', 'modalidad']);
        $pedido = $tareaAnterior->pedido;

        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw new \RuntimeException('No puede corregir este pedido.');
        }

        if ($tareaAnterior->estado !== PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA) {
            throw ValidationException::withMessages([
                'estado' => 'Solo puede corregir tareas con incidencia.',
            ]);
        }

        return DB::transaction(function () use ($tareaAnterior, $usuario, $almacenId, $productos, $observaciones, $pedido) {
            $this->transicionService->ejecutar(
                $tareaAnterior,
                PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
                $usuario->id,
                'cancelar_por_correccion',
                'Cancelada por corrección de Ventas.',
                ['tarea_sustituta_de' => $tareaAnterior->id],
                null,
                $usuario
            );

            $nueva = PedidoBmaTareaPreparacion::query()->create([
                'pedido_bma_id' => $pedido->id,
                'catalogo_modalidad_preparacion_id' => $tareaAnterior->catalogo_modalidad_preparacion_id,
                'almacen_id' => $almacenId,
                'area_responsable_codigo' => 'TIENDA',
                'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
                'solicitada_por_id' => $usuario->id,
                'solicitada_at' => now(),
                'fecha_limite' => $tareaAnterior->fecha_limite,
                'observaciones_solicitud' => $observaciones,
                'tarea_anterior_id' => $tareaAnterior->id,
            ]);

            $orden = 0;
            foreach ($productos as $p) {
                $nueva->productos()->create([
                    'producto_id' => $p['producto_id'] ?? null,
                    'sku' => $p['sku'] ?? null,
                    'descripcion_snapshot' => $p['descripcion_snapshot'],
                    'cantidad_solicitada' => max(1, (int) ($p['cantidad_solicitada'] ?? 1)),
                    'orden' => $orden++,
                ]);
            }

            $pedido->update([
                'almacen_id' => $almacenId,
                'consulta_actualizacion_pendiente' => false,
                'pesaje_respondido_at' => null,
                'pesaje_respondido_por_id' => null,
                'estatus_envio' => \App\Models\ControlPedidos\PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                'Ventas corrigió y reenvió la solicitud de preparación en Tienda.',
                AccionesHistorialPedidoBma::CORRECCION_PREPARACION_TIENDA
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_preparacion_tienda_corregida',
                'Ventas corrigió y reenvió una solicitud de preparación en Tienda.',
                ['control_pedidos.tienda.ver'],
                $usuario->id,
                false,
                ['url' => '/control-pedidos/tienda?tarea='.$nueva->id]
            );

            return $nueva->fresh(['modalidad', 'almacen', 'productos', 'pedido.cliente']);
        });
    }
}
