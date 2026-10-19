<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\AlmacenPdv;
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

    public function ejecutar(PedidoBma $pedido, int $usuarioId, ?array $baseCaptura = null): PedidoBma
    {
        $baseCaptura ??= ['revision_esperada' => (int) $pedido->revision_registro, 'base_captura' => $pedido->base_captura];
        return DB::transaction(function () use ($pedido, $usuarioId, $baseCaptura) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            app(ListarPedidosBmaService::class)->asegurarAcceso($pedido, User::findOrFail($usuarioId));
            $replay = $pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE && $pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE && !$pedido->tienePesajeRespondido();
            if (!$replay) \App\Support\ControlPedidos\RevisionCapturaPedido::comprobar($pedido, $baseCaptura);
            return $this->solicitar($pedido, $usuarioId);
        }, 3);
    }

    private function solicitar(PedidoBma $pedido, int $usuarioId): PedidoBma
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
                $pedido->consultaCedisModoRevisionMercancia()
                    ? 'Este pedido no puede solicitar consulta de mercancía en su estado actual.'
                    : 'Este pedido no puede solicitar pesaje en su estado actual.'
            );
        }

        if ($pedido->esTrasladoInternoAPdv()) {
            app(ValidarSucursalDestinoPedidoBma::class)->ejecutar($pedido, (int) $pedido->sucursal_destino_id, exigirSiAplica: true);
        }

        $pedido->loadMissing(['paqueteria', 'almacen.tipoAlmacen']);
        if (($pedido->origen?->requiere_logistica ?? true)
            && ! $pedido->catalogo_paqueteria_id
            && ! $pedido->esTrasladoInternoAPdv()
            && ! $pedido->esResguardoAbierto()) {
            throw new \InvalidArgumentException(
                'Seleccione la paquetería antes de solicitar la consulta a CEDIS.'
            );
        }

        $usuario = User::find($usuarioId);
        if ($usuario && $this->consultaCedisBloqueadaPorSalidaTienda($pedido, $usuario)) {
            throw new \RuntimeException(
                'La salida es un almacén de Tienda. Solicite la preparación en ese almacén; la consulta de mercancía de CEDIS no aplica.'
            );
        }

        if (blank($pedido->folio_remision)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['folio_remision' => 'Capture el folio del pedido antes de solicitar la consulta.']);
        }

        if (! $pedido->tieneSoporteSolicitud()) {
            throw new \InvalidArgumentException('Debe adjuntar la cotización o el soporte del pedido antes de solicitar la consulta a CEDIS.');
        }

        MaquinaEstadosPedidoBma::assertTransicion(
            $pedido->estatus?->fase_ciclo,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
        );

        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus de consulta pendiente.');
        }

        $esMercancia = $pedido->consultaCedisModoRevisionMercancia();
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

    /**
     * La consulta CEDIS se conserva si la salida no es un PDV habilitado para preparación.
     * El origen comercial Tienda no basta para bloquearla.
     */
    private function consultaCedisBloqueadaPorSalidaTienda(PedidoBma $pedido, User $usuario): bool
    {
        if ($pedido->esTrasladoInternoAPdv() || ! $pedido->esConsultaMercancia() || ! $this->preparacionConfig->activo() || ! $this->preparacionConfig->usuarioHabilitado($usuario)) {
            return false;
        }

        $almacenId = (int) $pedido->almacen_id;
        if ($almacenId <= 0 || ! $this->preparacionConfig->almacenPermitido($almacenId)) {
            return false;
        }

        return AlmacenPdv::esAlmacen(Almacen::query()->find($almacenId));
    }
}
