<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class LiberarResguardoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->es_resguardo) {
            throw new \InvalidArgumentException('Este pedido no está en resguardo.');
        }

        $fase = $pedido->estatus?->fase_ciclo;
        $enPendienteAuxiliar = $fase === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR;
        $enCedisConResguardo = $fase === CatalogoEstatusPedido::FASE_EN_CEDIS;

        if (!$enPendienteAuxiliar && !$enCedisConResguardo) {
            throw new \RuntimeException('Solo se puede liberar resguardo en pedidos pendientes de revisión o en CEDIS.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $enPendienteAuxiliar) {
            $estatusAnterior = $pedido->estatus;
            $listoParaCedis = $pedido->tienePagoValidado() && $pedido->tieneRemision();

            // Ya está en CEDIS con flag: solo liberar para habilitar empacar.
            if (!$enPendienteAuxiliar) {
                $pedido->update(['es_resguardo' => false]);

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior->id,
                    $estatusAnterior->id,
                    'Resguardo liberado. Pedido pendiente de empaque.'
                );

                return $pedido->fresh(['cliente', 'estatus', 'origen', 'documentos', 'almacen', 'banco', 'direccionVigente']);
            }

            // Aún en auxiliar: si ya tiene pago+remisión, avanza a CEDIS sin resguardo.
            if ($listoParaCedis) {
                $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS)
                    ?? CatalogoEstatusPedido::porCodigo('AMARILLO');

                if (!$estatusNuevo) {
                    throw new \RuntimeException('No se encontró el estatus EN_CEDIS.');
                }

                $pedido->update([
                    'es_resguardo' => false,
                    'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                ]);

                $this->historialService->registrarTransicion(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior,
                    $estatusNuevo,
                    'Resguardo liberado; pedido enviado a CEDIS.'
                );
            } else {
                $pedido->update(['es_resguardo' => false]);

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior->id,
                    $estatusAnterior->id,
                    'Resguardo liberado por el auxiliar.'
                );
            }

            return $pedido->fresh(['cliente', 'estatus', 'origen', 'documentos', 'almacen', 'banco', 'direccionVigente']);
        });
    }
}
