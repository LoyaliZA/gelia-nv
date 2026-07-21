<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class MarcarEnviadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO) {
            throw new \RuntimeException('El pedido no está pendiente de envío.');
        }

        if ($pedido->empacado_at === null) {
            throw new \RuntimeException('El pedido debe estar empacado antes de marcarlo como enviado.');
        }

        $pedido->loadMissing(['paqueteria', 'origen']);

        if ($pedido->ofreceRastreo() && empty($pedido->numero_rastreo)) {
            throw new \RuntimeException('El pedido requiere número de guía antes de marcarlo como enviado.');
        }

        $estatusEnviado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO);

        if (!$estatusEnviado) {
            throw new \RuntimeException('No se encontró el estatus ENVIADO.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $estatusEnviado) {
            $estatusAnterior = $pedido->estatus;

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusEnviado->id,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusEnviado,
                'Pedido marcado como enviado; sale a recolección.'
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'vendedor',
            ]);
        });
    }
}
