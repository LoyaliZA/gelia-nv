<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarSalidaTrasladoTiendaService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        ?int $versionEsperada = null,
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.tienda.trasladar')) {
            throw new \RuntimeException('No tiene permiso para confirmar salida a CEDIS.');
        }

        return DB::transaction(function () use ($tarea, $usuario, $versionEsperada) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);

            if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo puede confirmar salida cuando la tarea está lista para traslado.',
                ]);
            }

            if (! $tarea->solicitud_traspaso_id) {
                throw ValidationException::withMessages([
                    'traspaso' => 'No hay traspaso vinculado. Genere el traspaso antes de confirmar salida.',
                ]);
            }

            $tarea->update([
                'enviada_cedis_por_id' => $usuario->id,
                'enviada_cedis_at' => now(),
            ]);

            $tarea = $this->transicionService->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
                $usuario->id,
                'confirmar_salida_cedis',
                'Tienda confirmó salida hacia CEDIS.',
                ['solicitud_traspaso_id' => $tarea->solicitud_traspaso_id],
                $versionEsperada,
                $usuario
            );

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor', 'estatus'])->first();
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                'Mercancía en traslado hacia CEDIS.',
                AccionesHistorialPedidoBma::TRASLADO_PREPARACION_EN_CAMINO
            );

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_preparacion_tienda_en_traslado',
                'La mercancía salió de Tienda hacia CEDIS.',
                [],
                $usuario->id,
                true,
                ['url' => '/control-pedidos?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $tarea->fresh(['modalidad', 'almacen', 'productos', 'solicitudTraspaso']);
        });
    }
}
