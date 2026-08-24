<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

class SolicitarPesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private PreparacionTiendaConfig $preparacionConfig,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen']);
        $fase = $pedido->estatus?->fase_ciclo;

        // Idempotente: ya enviada a CEDIS (doble clic / UI stale) → éxito sin error.
        if ($pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE
            && $fase === CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
            && ! $pedido->tienePesajeRespondido()) {
            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja', 'origen',
            ]);
        }

        if (! $pedido->puedeSolicitarPesaje()) {
            throw new \RuntimeException(
                $pedido->esConsultaMercancia()
                    ? 'Este pedido no puede solicitar consulta de mercancía en su estado actual.'
                    : 'Este pedido no puede solicitar pesaje en su estado actual.'
            );
        }

        $usuario = User::find($usuarioId);
        if ($pedido->esConsultaMercancia()
            && $usuario
            && $this->preparacionConfig->activo()
            && $this->preparacionConfig->usuarioHabilitado($usuario)) {
            throw new \RuntimeException(
                'Este pedido debe solicitar preparación en Tienda. Use la opción de recolección en tienda.'
            );
        }

        if (! $pedido->tienePdfPedido()) {
            throw new \InvalidArgumentException('Debe adjuntar el PDF o una foto del pedido antes de solicitar la consulta a CEDIS.');
        }

        MaquinaEstadosPedidoBma::assertTransicion(
            $pedido->estatus?->fase_ciclo,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
        );

        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus de consulta pendiente.');
        }

        $esMercancia = $pedido->esConsultaMercancia();
        $label = $esMercancia ? 'Consulta de mercancía' : 'Consulta de pesaje';

        return DB::transaction(function () use ($pedido, $usuarioId, $estatusNuevo, $esMercancia, $label) {
            $estatusAnterior = $pedido->estatus;

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
                'pesaje_solicitado_at' => now(),
                'pesaje_respondido_at' => null,
                'pesaje_respondido_por_id' => null,
                'motivo_repesaje' => null,
                'consulta_cerrada_at' => null,
                'consulta_cerrada_por_id' => null,
                'consulta_actualizacion_pendiente' => false,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusAnterior->id,
                $estatusNuevo->id,
                "{$label} enviada a CEDIS.",
                AccionesHistorialPedidoBma::SOLICITUD_PESAJE
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_consulta_pesaje',
                $esMercancia
                    ? 'Nueva consulta de mercancía pendiente'
                    : 'Nueva consulta de pesaje pendiente',
                ['control_pedidos.cedis'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_PESAJE']
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja', 'origen',
            ]);
        });
    }
}
