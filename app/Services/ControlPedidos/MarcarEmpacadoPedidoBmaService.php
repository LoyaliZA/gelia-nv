<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class MarcarEmpacadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esGestionablePorCedis()) {
            throw new \RuntimeException('El pedido no está en la bandeja de CEDIS.');
        }

        if (!$pedido->puedeMarcarEmpacado()) {
            throw new \RuntimeException('Este pedido no puede marcarse como empacado.');
        }

        if (!$pedido->tienePagoValidado() || !$pedido->tieneRemision()) {
            throw new \RuntimeException('El pedido debe tener pago validado y remisión adjunta.');
        }

        if ($pedido->es_resguardo) {
            throw new \RuntimeException('Un pedido en resguardo no puede marcarse como empacado. Libere el resguardo primero.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido->loadMissing(['paqueteria', 'origen']);
            $estatusAnterior = $pedido->estatus;

            $tieneGuia = !empty($pedido->numero_rastreo);
            $faseDestino = (!$pedido->ofreceRastreo() || $tieneGuia)
                ? CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
                : CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA;

            $estatusNuevo = CatalogoEstatusPedido::porFase($faseDestino);

            if (!$estatusNuevo) {
                throw new \RuntimeException("No se encontró el estatus {$faseDestino}.");
            }

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'empacado_at' => now(),
                'empacado_por_id' => $usuarioId,
                'detalle_incidencia_empaque' => null,
                'incidencia_empaque_at' => null,
                'incidencia_empaque_por_id' => null,
            ]);

            $comentario = $faseDestino === CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA
                ? 'Pedido empacado; pendiente de captura de guía.'
                : ($tieneGuia
                    ? 'Pedido empacado; guía ya asignada, pendiente de envío.'
                    : 'Pedido empacado; pendiente de envío.');

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                $comentario
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacen', 'origen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'incidenciaEmpaquePor',
            ]);
        });
    }
}
