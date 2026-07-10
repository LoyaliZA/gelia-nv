<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class ValidarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede validar el pago de pedidos pendientes de revisión.');
        }

        if ($pedido->documentos()->where('tipo', 'comprobante')->count() === 0) {
            throw new \RuntimeException('El pedido no tiene comprobantes de pago adjuntos.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido->update([
                'pago_validado_at' => now(),
                'pago_validado_por_id' => $usuarioId,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $pedido->estatus,
                $pedido->estatus,
                'Pago validado por auxiliar.'
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacenSalida',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
            ]);
        });
    }
}
