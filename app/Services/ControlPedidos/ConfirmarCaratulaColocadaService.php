<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarCaratulaColocadaService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private ResponderPreparacionTiendaService $responderService,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        ?int $versionEsperada = null,
    ): PedidoBmaTareaPreparacion {
        if (! $usuario->can('control_pedidos.tienda.confirmar_caratula')) {
            throw new \RuntimeException('No tiene permiso para confirmar colocación de carátula.');
        }

        return DB::transaction(function () use ($tarea, $usuario, $versionEsperada) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);

            if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo puede confirmar colocación en tareas listas para carátula.',
                ]);
            }

            $caratula = $tarea->caratulas()
                ->where('estado', PedidoBmaCaratula::ESTADO_GENERADA)
                ->orderByDesc('version')
                ->first();

            if (! $caratula || ! $caratula->ruta_pdf) {
                throw ValidationException::withMessages([
                    'caratula' => 'Debe generar la carátula PDF antes de confirmar la colocación.',
                ]);
            }

            $caratula->update([
                'estado' => PedidoBmaCaratula::ESTADO_COLOCADA,
                'colocada_por_id' => $usuario->id,
                'colocada_at' => now(),
            ]);

            $tarea = $this->transicionService->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                $usuario->id,
                'caratula_colocada',
                'Carátula colocada en el paquete.',
                ['caratula_id' => $caratula->id, 'version' => $caratula->version],
                $versionEsperada,
                $usuario,
                true
            );

            $this->responderService->aplicarSincronizacionPedido($tarea, $usuario->id);

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor', 'estatus'])->first();
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                "Carátula v{$caratula->version} confirmada como colocada.",
                AccionesHistorialPedidoBma::CARATULA_COLOCADA
            );

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_preparacion_tienda_respondida',
                'Tienda colocó la carátula y respondió la preparación municipal. Confirma con el cliente y cierra la consulta.',
                [],
                $usuario->id,
                true,
                ['url' => '/control-pedidos?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $tarea->fresh(['modalidad', 'almacen', 'caratulas', 'pedido.cliente', 'paqueteria']);
        });
    }
}
