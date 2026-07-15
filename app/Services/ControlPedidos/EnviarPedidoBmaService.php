<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\Direcciones\CrearSnapshotDireccionPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EnviarPedidoBmaService
{
    use ValidacionCamposPedidoBma;

    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private CrearSnapshotDireccionPedido $crearSnapshot,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Solo se pueden enviar pedidos en borrador o rechazados.');
        }

        $this->validarCamposRequeridos($pedido);

        if (config('control_pedidos.direcciones_normalizadas')) {
            $pedido->loadMissing('origen');
            if ($pedido->origen?->requiere_logistica && ! $pedido->cliente_direccion_id) {
                throw new \InvalidArgumentException('Debe seleccionar una dirección de envío verificada.');
            }
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)
                ?? CatalogoEstatusPedido::porCodigo('AZUL_1');

            if (!$estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus PENDIENTE_AUXILIAR.');
            }

            if (config('control_pedidos.direcciones_normalizadas') || $pedido->cliente_direccion_id) {
                $this->crearSnapshot->ejecutar($pedido, $usuarioId);
                $pedido->refresh();
            }

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'motivo_rechazo' => null,
                'pago_validado_at' => null,
                'pago_validado_por_id' => null,
            ]);

            $this->eliminarRemisiones($pedido);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                'Pedido enviado a revisión del auxiliar.'
            );

            return $pedido->fresh(['cliente', 'estatus', 'documentos', 'almacen', 'banco', 'direccionVigente']);
        });
    }

    private function eliminarRemisiones(PedidoBma $pedido): void
    {
        $remisiones = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->get();

        foreach ($remisiones as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
