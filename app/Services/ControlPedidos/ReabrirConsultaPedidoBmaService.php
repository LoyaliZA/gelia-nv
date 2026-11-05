<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;

class ReabrirConsultaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing(['estatus', 'origen', 'vendedor']);
            $actor = User::findOrFail($usuarioId);
            abort_unless($actor->can('control_pedidos.crear') && VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido), 403);
            if (! $pedido->consultaCerrada()) {
                return $pedido;
            }
            $tienda = ($pedido->solicitud_preparacion_json['modalidad_entrega'] ?? '') === 'tienda';
            if ((! $pedido->esEditablePorVendedora() && ! $tienda) || $pedido->cancelado_at || in_array($pedido->estatus?->fase_ciclo, ['ENVIADO', 'EN_RUTA', 'ENTREGADO', 'CANCELADO'], true) || ($tienda && ($pedido->empacado_at || InstruccionEntrega::mercanciaYaSalio($pedido)))) {
                throw new \RuntimeException('La consulta no puede reabrirse después de preparar o trasladar la mercancía.');
            }
            $json = $pedido->solicitud_preparacion_json ?? [];
            if ($tienda) {
                $json['reapertura_disponibilidad'] = ['por_id' => $actor->id, 'por' => $actor->name, 'fecha' => now()->toIso8601String(), 'motivo' => $pedido->tieneSinExistenciaAbierta() ? 'Revisión de piezas sin existencias' : 'Revisión de consulta solicitada por Ventas'];
            }
            $estatus = $pedido->estatus;
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'estatus_envio' => $tienda && ($json['area_revision'] ?? '') === 'control_pedidos' ? PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE : $pedido->estatus_envio,
                'consulta_cerrada_at' => null,
                'consulta_cerrada_por_id' => null,
            ]);

            $label = $pedido->consultaCedisModoRevisionMercancia() ? 'Consulta de mercancía' : 'Consulta de pesaje';
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                "{$label} reabierta por Ventas.",
                AccionesHistorialPedidoBma::REABRIR_CONSULTA
            );

            if ($tienda) {
                app(AccionesDisponibilidadTiendaService::class)->notificar($pedido, $actor, 'Ventas reabrió la consulta. Revise los cambios solicitados antes de preparar la mercancía.');
            }

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'origen',
            ]);
        });
    }
}
