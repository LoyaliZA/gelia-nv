<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Support\Facades\DB;

/**
 * Actualización incremental de consulta CEDIS (anexo/retiro/surtido).
 * Conserva pesaje_respondido_at y costo_envio hasta que CEDIS confirme cambios.
 */
class ActualizarConsultaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen']);

        if (! $pedido->puedeSolicitarRepesaje()) {
            throw new \RuntimeException('No se puede actualizar la consulta en este pedido (empacado o sin respuesta CEDIS previa).');
        }

        $motivo = trim($motivo);
        if (! in_array($motivo, PedidoBma::MOTIVOS_REPESAJE, true)) {
            throw new \InvalidArgumentException('Debe indicar un motivo válido (anexo, retiro, surtido u otro).');
        }

        if (! $pedido->tienePdfPedido()) {
            throw new \InvalidArgumentException('Debe haber un PDF o foto del pedido adjunto para actualizar la consulta.');
        }

        if ($motivo === PedidoBma::MOTIVO_REPESAJE_ANEXO_PIEZAS && ! $pedido->tieneAnexoPiezas()) {
            throw new \InvalidArgumentException('Debe adjuntar el PDF o foto de las piezas adicionales antes de actualizar la consulta.');
        }

        $etiquetas = [
            PedidoBma::MOTIVO_REPESAJE_ANEXO_PIEZAS => 'anexó piezas',
            PedidoBma::MOTIVO_REPESAJE_QUITA_PIEZAS => 'retiró piezas',
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
                throw new \RuntimeException('No se encontró el estatus de consulta pendiente.');
            }
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $motivo, $etiquetas, $estatusNuevo) {
            $estatus = $pedido->estatus;
            $esMercancia = $pedido->esConsultaMercancia();
            $label = $esMercancia ? 'Consulta de mercancía' : 'Consulta de pesaje';

            $datos = [
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
                'pesaje_solicitado_at' => now(),
                // Conservar respuesta previa para precarga CEDIS.
                'motivo_repesaje' => $motivo,
                'consulta_actualizacion_pendiente' => true,
                'consulta_cerrada_at' => null,
                'consulta_cerrada_por_id' => null,
            ];

            if ($estatusNuevo) {
                $datos['catalogo_estatus_pedido_id'] = $estatusNuevo->id;
            }

            $pedido->update($datos);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatusNuevo?->id ?? $estatus->id,
                "{$label} actualizada: el cliente {$etiquetas[$motivo]}. CEDIS debe confirmar; costo de envío se conserva hasta cambios de peso/cajas.",
                AccionesHistorialPedidoBma::ACTUALIZAR_CONSULTA
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_consulta_pesaje',
                $esMercancia
                    ? 'Actualización de consulta de mercancía'
                    : 'Actualización de consulta de pesaje',
                ['control_pedidos.cedis'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_PESAJE']
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja', 'origen',
                'revisionesProducto',
            ]);
        });
    }
}
