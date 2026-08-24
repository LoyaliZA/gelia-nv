<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiberarTareaPreparacionService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        ?string $motivo = null,
        ?int $versionEsperada = null,
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.tienda.liberar')) {
            throw new \RuntimeException('No tiene permiso para liberar mercancía.');
        }

        return DB::transaction(function () use ($tarea, $usuario, $motivo, $versionEsperada) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);

            if (! in_array($tarea->estado, [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
            ], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo puede liberar tareas respondidas o con liberación solicitada.',
                ]);
            }

            $tarea = $this->transicionService->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
                $usuario->id,
                'liberar',
                $motivo ?: 'Mercancía liberada manualmente.',
                null,
                $versionEsperada,
                $usuario
            );

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor', 'estatus'])->first();
            $pedido->update(['es_resguardo' => false]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                'Mercancía liberada en Tienda.',
                AccionesHistorialPedidoBma::LIBERACION_PREPARACION_TIENDA
            );

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_preparacion_tienda_liberada',
                'La mercancía resguardada en Tienda fue liberada.',
                [],
                $usuario->id,
                true
            );

            return $tarea->fresh(['modalidad', 'almacen', 'productos']);
        });
    }
}
