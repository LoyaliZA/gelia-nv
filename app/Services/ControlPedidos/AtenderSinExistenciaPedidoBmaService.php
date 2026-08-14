<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\User;
use App\Services\SaldosAFavor\ReconciliarTotalPedidoSafService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AtenderSinExistenciaPedidoBmaService
{
    use ResuelveDatosPedidoBma;

    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private ReconciliarTotalPedidoSafService $reconciliarSaf,
        private SolicitarRepesajePedidoBmaService $repesajeService,
        private CancelarPedidoBmaService $cancelarService,
    ) {}

    /**
     * @param  array{
     *   nota?: string|null,
     *   total_mercancia?: float|string|null,
     *   cantidad_piezas?: int|string|null,
     *   costo_envio?: float|string|null,
     *   aplica_seguro?: bool|string|null,
     *   saldo_a_favor?: float|string|null,
     *   solicitar_repesaje?: bool|string|null,
     *   comentario_cancelacion?: string|null
     * }  $datos
     */
    public function ejecutar(
        PedidoBma $pedido,
        User $actor,
        int $revisionId,
        string $accion,
        array $datos = []
    ): PedidoBma {
        $pedido->loadMissing(['estatus', 'revisionesProducto', 'documentos', 'paqueteria']);

        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido)) {
            throw new \RuntimeException('Solo la vendedora del pedido puede atender sin existencias.');
        }

        if ($pedido->empacado_at !== null) {
            throw new \RuntimeException('El pedido ya está empacado. Revierta el empaque antes de atender sin existencias.');
        }

        $revision = $pedido->revisionesProducto->firstWhere('id', $revisionId);
        if (! $revision || ! $revision->estaSinExistenciaAbierta()) {
            throw new \InvalidArgumentException('No hay una pieza sin existencias abierta para atender.');
        }

        $accion = trim($accion);
        if ($accion === PedidoBmaRevisionProducto::RESOLUCION_STOCK_OK) {
            throw new \InvalidArgumentException('Solo CEDIS confirma que ya hay existencias.');
        }

        if ($accion === 'cancelar') {
            if (! $actor->can('control_pedidos.cancelar')) {
                throw new \RuntimeException('No tienes permiso para cancelar el pedido.');
            }

            return $this->cancelarService->ejecutar($pedido, (int) $actor->id, [
                'motivo' => 'sin_stock',
                'comentario' => (string) ($datos['comentario_cancelacion'] ?? $datos['nota'] ?? ''),
            ]);
        }

        if (! in_array($accion, PedidoBmaRevisionProducto::RESOLUCIONES, true)) {
            throw new \InvalidArgumentException('Acción de sin existencias no válida.');
        }

        $nota = trim((string) ($datos['nota'] ?? ''));
        if (in_array($accion, [
            PedidoBmaRevisionProducto::RESOLUCION_CONTACTAR,
            PedidoBmaRevisionProducto::RESOLUCION_ESPERAR,
        ], true) && $nota === '') {
            throw new \InvalidArgumentException('Indique una nota (qué se acordó con el cliente).');
        }

        if ($accion === PedidoBmaRevisionProducto::RESOLUCION_SUSTITUIR
            && ! $pedido->tienePdfPedido()
            && ! $pedido->tieneAnexoPiezas()) {
            throw new \InvalidArgumentException('Para sustituir, adjunte el PDF o anexo del surtido nuevo.');
        }

        return DB::transaction(function () use ($pedido, $actor, $revision, $accion, $datos, $nota) {
            $estatusAnterior = $pedido->estatus;
            $totalAntes = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);

            $revision->update([
                'resolucion' => $accion,
                'resolucion_nota' => $nota !== '' ? $nota : null,
                'resolucion_por_id' => $actor->id,
                'resolucion_at' => now(),
            ]);

            $detalle = sprintf(
                'Sin existencias: %s — %s. %s',
                $revision->descripcion_producto,
                PedidoBmaRevisionProducto::LABELS_RESOLUCION[$accion] ?? $accion,
                $nota !== '' ? $nota : 'Sin nota.'
            );

            if (in_array($accion, [
                PedidoBmaRevisionProducto::RESOLUCION_CONTACTAR,
                PedidoBmaRevisionProducto::RESOLUCION_ESPERAR,
            ], true)) {
                $this->historialService->ejecutar(
                    $pedido->id,
                    (int) $actor->id,
                    $estatusAnterior?->id,
                    $estatusAnterior?->id ?? $pedido->catalogo_estatus_pedido_id,
                    $detalle,
                    AccionesHistorialPedidoBma::DECISION_SIN_EXISTENCIA
                );

                return $pedido->fresh(['estatus', 'revisionesProducto', 'documentos']);
            }

            $totales = $this->resolverTotales([
                'total_mercancia' => $datos['total_mercancia'] ?? $pedido->total_mercancia,
                'costo_envio' => array_key_exists('costo_envio', $datos)
                    ? $datos['costo_envio']
                    : $pedido->costo_envio,
                'aplica_seguro' => array_key_exists('aplica_seguro', $datos)
                    ? $datos['aplica_seguro']
                    : $pedido->aplica_seguro,
                'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
                'saldo_a_favor' => $datos['saldo_a_favor'] ?? $pedido->saldo_a_favor,
            ]);

            $piezas = array_key_exists('cantidad_piezas', $datos) && $datos['cantidad_piezas'] !== '' && $datos['cantidad_piezas'] !== null
                ? max(0, (int) $datos['cantidad_piezas'])
                : $pedido->cantidad_piezas;

            $mercanciaAntes = (float) $pedido->total_mercancia;
            $envioAntes = $pedido->costo_envio;
            $seguroAntes = (bool) $pedido->aplica_seguro;

            $pedido->update(array_merge($totales, [
                'cantidad_piezas' => $piezas,
            ]));

            $this->reconciliarSaf->handle(
                $pedido->fresh(),
                $totalAntes,
                (int) $actor->id,
                'ajuste_admin',
                'Reconciliación tras atender sin existencias ('.$accion.')'
            );

            $detalle .= sprintf(
                ' Totales: mercancía $%s → $%s, envío $%s → $%s, total $%s.',
                number_format($mercanciaAntes, 2, '.', ''),
                number_format((float) $totales['total_mercancia'], 2, '.', ''),
                number_format((float) ($envioAntes ?? 0), 2, '.', ''),
                number_format((float) ($totales['costo_envio'] ?? 0), 2, '.', ''),
                number_format((float) $totales['total_a_cobrar'], 2, '.', '')
            );

            $this->historialService->ejecutar(
                $pedido->id,
                (int) $actor->id,
                $estatusAnterior?->id,
                $estatusAnterior?->id ?? $pedido->catalogo_estatus_pedido_id,
                $detalle,
                AccionesHistorialPedidoBma::DECISION_SIN_EXISTENCIA
            );

            $pedido = $pedido->fresh(['estatus', 'revisionesProducto', 'documentos', 'origen']);
            $solicitarRepesaje = filter_var($datos['solicitar_repesaje'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($accion === PedidoBmaRevisionProducto::RESOLUCION_SUSTITUIR) {
                $solicitarRepesaje = true;
            }

            if ($solicitarRepesaje && $pedido->puedeSolicitarRepesaje()) {
                $motivo = $accion === PedidoBmaRevisionProducto::RESOLUCION_SUSTITUIR
                    ? PedidoBma::MOTIVO_REPESAJE_CAMBIO_SURTIDO
                    : PedidoBma::MOTIVO_REPESAJE_QUITA_PIEZAS;

                return $this->repesajeService->ejecutar($pedido, (int) $actor->id, $motivo);
            }

            $fase = $pedido->estatus?->fase_ciclo;
            $cambioMercancia = abs($mercanciaAntes - (float) $totales['total_mercancia']) > 0.009
                || (string) ($envioAntes ?? '') !== (string) ($totales['costo_envio'] ?? '')
                || $seguroAntes !== (bool) $totales['aplica_seguro'];

            if ($cambioMercancia && in_array($fase, [
                CatalogoEstatusPedido::FASE_EN_CEDIS,
                CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            ], true)) {
                return $this->devolverAAuditoria($pedido, (int) $actor->id, $estatusAnterior);
            }

            return $pedido->fresh(['estatus', 'revisionesProducto', 'documentos']);
        });
    }

    private function devolverAAuditoria(
        PedidoBma $pedido,
        int $usuarioId,
        ?CatalogoEstatusPedido $estatusAnterior
    ): PedidoBma {
        MaquinaEstadosPedidoBma::assertTransicion(
            $estatusAnterior?->fase_ciclo,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR
        );
        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus de auditoría.');
        }

        $this->eliminarRemisiones($pedido);

        $pedido->update([
            'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            'pago_validado_at' => null,
            'pago_validado_por_id' => null,
        ]);

        $this->historialService->registrarTransicion(
            $pedido->id,
            $usuarioId,
            $estatusAnterior,
            $estatusNuevo,
            'Sin existencias atendida: se invalidó remisión y pago para recargar en auditoría.',
            AccionesHistorialPedidoBma::DECISION_SIN_EXISTENCIA
        );

        $pedido = $pedido->fresh(['estatus', 'revisionesProducto', 'documentos', 'cliente', 'vendedor']);
        $q = urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id));
        $this->notificarService->ejecutar(
            $pedido,
            'pedido_pendiente_auxiliar',
            'Pedido volvió a auditoría tras ajuste por sin existencias',
            ['control_pedidos.auditar'],
            $usuarioId,
            false,
            ['url' => '/control-pedidos/auditar?tab=PENDIENTES&q='.$q]
        );

        return $pedido;
    }

    private function eliminarRemisiones(PedidoBma $pedido): void
    {
        $remisiones = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->get();
        foreach ($remisiones as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
