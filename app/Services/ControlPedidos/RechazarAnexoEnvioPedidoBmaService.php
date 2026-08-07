<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAnexoEnvio;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class RechazarAnexoEnvioPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): PedidoBma
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('El motivo de rechazo es obligatorio.');
        }

        if (!$pedido->tieneAnexoEnvioPorRevisar()) {
            throw new \RuntimeException('El pedido no tiene un anexo de envío pendiente de revisión.');
        }

        $anexo = $pedido->anexosEnvio()
            ->where('estatus', PedidoBmaAnexoEnvio::ESTATUS_PENDIENTE)
            ->latest('id')
            ->first();

        if (!$anexo) {
            throw new \RuntimeException('No se encontró el anexo de envío pendiente.');
        }

        return DB::transaction(function () use ($pedido, $anexo, $usuarioId, $motivo) {
            $anexo->update([
                'estatus' => PedidoBmaAnexoEnvio::ESTATUS_RECHAZADO,
                'motivo_rechazo' => $motivo,
                'validado_por_id' => $usuarioId,
                'validado_at' => now(),
            ]);

            $pedido->update([
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_ANEXO_RECHAZADO,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Anexo de pago de envío rechazado: '.$motivo,
                AccionesHistorialPedidoBma::RECHAZAR_ANEXO
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'tipoOperacionEnvio', 'anexosEnvio.banco',
                'anexosEnvio.registradoPor', 'anexosEnvio.validadoPor',
            ]);
        });
    }
}
