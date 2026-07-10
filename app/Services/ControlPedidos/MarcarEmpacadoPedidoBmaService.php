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

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_RUTA)
                ?? CatalogoEstatusPedido::porCodigo('VERDE');

            if (!$estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus EN_RUTA.');
            }

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'empacado_at' => now(),
                'empacado_por_id' => $usuarioId,
                'detalle_incidencia_empaque' => null,
                'incidencia_empaque_at' => null,
                'incidencia_empaque_por_id' => null,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                'Pedido marcado como empacado.'
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacenSalida',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'incidenciaEmpaquePor',
            ]);
        });
    }
}
