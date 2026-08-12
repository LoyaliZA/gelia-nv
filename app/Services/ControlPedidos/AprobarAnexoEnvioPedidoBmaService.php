<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAnexoEnvio;
use App\Services\SaldosAFavor\ReconciliarTotalPedidoSafService;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class AprobarAnexoEnvioPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private ReconciliarTotalPedidoSafService $reconciliarSaf,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
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

        return DB::transaction(function () use ($pedido, $anexo, $usuarioId) {
            $totalAntes = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);
            $monto = (float) $anexo->monto;
            $mercancia = (float) $pedido->total_mercancia;
            $seguro = (bool) $pedido->aplica_seguro;
            $costoSeguro = (float) ($pedido->costo_seguro ?? 0);
            $saldoFavor = (float) ($pedido->saldo_a_favor ?? 0);

            $anexo->update([
                'estatus' => PedidoBmaAnexoEnvio::ESTATUS_APROBADO,
                'validado_por_id' => $usuarioId,
                'validado_at' => now(),
                'motivo_rechazo' => null,
            ]);

            $pedido->update([
                'costo_envio' => $monto,
                'total_a_cobrar' => PedidoBma::calcularTotal(
                    $mercancia,
                    $monto,
                    $seguro,
                    $costoSeguro,
                    $saldoFavor
                ),
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            ]);

            $this->reconciliarSaf->handle(
                $pedido->fresh(),
                $totalAntes,
                $usuarioId,
                'sobrante_envio',
                'Reconciliación tras aprobar anexo de envío'
            );

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                sprintf(
                    'Anexo de pago de envío aprobado ($%s). Costo de envío actualizado.',
                    number_format($monto, 2, '.', ',')
                ),
                AccionesHistorialPedidoBma::APROBAR_ANEXO
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'tipoOperacionEnvio', 'anexosEnvio.banco',
                'anexosEnvio.registradoPor', 'anexosEnvio.validadoPor', 'banco',
            ]);
        });
    }
}
