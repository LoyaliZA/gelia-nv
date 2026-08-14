<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAnexoEnvio;
use App\Services\SaldosAFavor\ReconciliarTotalPedidoSafService;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

class SolicitarRepesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private ReconciliarTotalPedidoSafService $reconciliarSaf,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): PedidoBma
    {
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeSolicitarRepesaje()) {
            throw new \RuntimeException('No se puede solicitar re-pesaje en este pedido (empacado o sin pesaje previo).');
        }

        $motivo = trim($motivo);
        if (! in_array($motivo, PedidoBma::MOTIVOS_REPESAJE, true)) {
            throw new \InvalidArgumentException('Debe indicar un motivo válido de re-pesaje (cambio de pedido).');
        }

        if (! $pedido->tienePdfPedido()) {
            throw new \InvalidArgumentException('Debe haber un PDF o foto del pedido adjunto para el re-pesaje.');
        }

        if ($motivo === PedidoBma::MOTIVO_REPESAJE_ANEXO_PIEZAS && ! $pedido->tieneAnexoPiezas()) {
            throw new \InvalidArgumentException('Debe adjuntar el PDF o foto de las piezas adicionales antes de solicitar el re-pesaje.');
        }

        $etiquetas = [
            PedidoBma::MOTIVO_REPESAJE_ANEXO_PIEZAS => 'anexó piezas',
            PedidoBma::MOTIVO_REPESAJE_QUITA_PIEZAS => 'quitó piezas',
            PedidoBma::MOTIVO_REPESAJE_CAMBIO_SURTIDO => 'cambió el surtido',
            PedidoBma::MOTIVO_REPESAJE_OTRO => 'otro cambio',
        ];

        $estatusNuevo = null;
        $faseActual = $pedido->estatus?->fase_ciclo;
        if (in_array($faseActual, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        ], true)) {
            MaquinaEstadosPedidoBma::assertTransicion(
                $faseActual,
                CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
            );
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
            if (! $estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus de pesaje pendiente.');
            }
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $motivo, $etiquetas, $estatusNuevo) {
            $estatus = $pedido->estatus;
            $totalAntes = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);

            $pedido->anexosEnvio()
                ->where('estatus', PedidoBmaAnexoEnvio::ESTATUS_PENDIENTE)
                ->update([
                    'estatus' => PedidoBmaAnexoEnvio::ESTATUS_RECHAZADO,
                    'motivo_rechazo' => 'Invalidado por solicitud de re-pesaje.',
                    'validado_at' => now(),
                    'validado_por_id' => $usuarioId,
                ]);

            $mercancia = (float) $pedido->total_mercancia;
            $seguro = (bool) $pedido->aplica_seguro;
            $costoSeguro = (float) ($pedido->costo_seguro ?? 0);
            $saldoFavor = (float) ($pedido->saldo_a_favor ?? 0);

            $datos = [
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
                'pesaje_solicitado_at' => now(),
                'pesaje_respondido_at' => null,
                'pesaje_respondido_por_id' => null,
                'motivo_repesaje' => $motivo,
                'costo_envio' => null,
                'total_a_cobrar' => PedidoBma::calcularTotal($mercancia, 0, $seguro, $costoSeguro, $saldoFavor),
            ];

            if ($estatusNuevo) {
                $datos['catalogo_estatus_pedido_id'] = $estatusNuevo->id;
            }

            $pedido->update($datos);

            $this->reconciliarSaf->handle(
                $pedido->fresh(),
                $totalAntes,
                $usuarioId,
                'sobrante_envio',
                'Reconciliación tras invalidar envío por re-pesaje'
            );

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatusNuevo?->id ?? $estatus->id,
                'Re-pesaje solicitado: el cliente '.$etiquetas[$motivo].'. Costo de envío invalidado.',
                AccionesHistorialPedidoBma::SOLICITUD_REPESAJE
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_consulta_pesaje',
                'Re-pesaje solicitado (cambio de pedido)',
                ['control_pedidos.cedis'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_PESAJE']
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja',
            ]);
        });
    }
}
