<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RechazarPagosPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
        private CoberturaPagoPedidoBmaService $cobertura,
        private SincronizarAplicacionesPedidoSafService $safPedido,
    ) {}

    /**
     * @param  list<int>  $pagoIds
     */
    public function ejecutar(PedidoBma $pedido, array $pagoIds, string $motivo, int $usuarioId): PedidoBma
    {
        if (! $pedido->esAuditablePorAuxiliar()) {
            throw new RuntimeException('Solo se pueden rechazar pagos de pedidos pendientes de revisión.');
        }

        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new InvalidArgumentException('Indique un motivo de rechazo (mínimo 5 caracteres).');
        }

        $pagoIds = array_values(array_unique(array_map('intval', $pagoIds)));
        if ($pagoIds === []) {
            throw new InvalidArgumentException('Seleccione al menos una exhibición para rechazar.');
        }

        return DB::transaction(function () use ($pedido, $pagoIds, $motivo, $usuarioId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            $pagos = PedidoBmaPago::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereIn('id', $pagoIds)
                ->lockForUpdate()
                ->get();

            if ($pagos->count() !== count($pagoIds)) {
                throw new InvalidArgumentException('Uno o más pagos no pertenecen a este pedido.');
            }

            foreach ($pagos as $pago) {
                if (! $pago->activo_para_cobertura) {
                    throw new InvalidArgumentException(
                        "La exhibición #{$pago->numero_exhibicion} ya no está activa para cobertura."
                    );
                }
                if ($pago->sustituto()->exists()) {
                    throw new InvalidArgumentException(
                        "La exhibición #{$pago->numero_exhibicion} ya tiene un sustituto."
                    );
                }
            }

            $numeros = [];
            foreach ($pagos as $pago) {
                $pago->update([
                    'estado_revision' => PedidoBmaPago::REVISION_RECHAZADO,
                    'activo_para_cobertura' => false,
                    'rechazado_at' => now(),
                    'rechazado_por_id' => $usuarioId,
                    'motivo_rechazo' => $motivo,
                    'observaciones' => $motivo,
                    'revisado_por_id' => $usuarioId,
                    'revisado_at' => now(),
                ]);
                $numeros[] = '#'.$pago->numero_exhibicion;

                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    sprintf(
                        'Exhibición #%d rechazada. Motivo: %s. El comprobante se conserva y deja de contar para cobertura.',
                        $pago->numero_exhibicion,
                        $motivo
                    ),
                    AccionesHistorialPedidoBma::RECHAZO_EXHIBICION_PAGO
                );
            }

            $this->safPedido->liberarReservasPendientes($pedido, $usuarioId);

            $estatusAnterior = $pedido->estatus;
            MaquinaEstadosPedidoBma::assertTransicion(
                $estatusAnterior?->fase_ciclo,
                CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA
            );
            $estatusRechazado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA)
                ?? CatalogoEstatusPedido::porCodigo('NARANJA');

            if (! $estatusRechazado) {
                throw new RuntimeException('No se encontró el estatus RECHAZADO_VENDEDORA.');
            }

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusRechazado->id,
                'motivo_rechazo' => 'Comprobantes rechazados: '.implode(', ', $numeros).'. '.$motivo,
                'pago_validado_at' => null,
                'pago_validado_por_id' => null,
            ]);

            $this->historial->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusRechazado,
                'Pagos rechazados por auxiliar ('.implode(', ', $numeros).'): '.$motivo,
                AccionesHistorialPedidoBma::RECHAZO
            );

            $this->cobertura->calcular($pedido->fresh());

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'vendedor', 'pagosExhibicion.banco',
            ]);

            $this->notificar->ejecutar(
                $pedido,
                'pedido_rechazado_auxiliar',
                'Debe sustituir las exhibiciones '.implode(', ', $numeros).' del pedido '
                    .($pedido->folio_remision ?: $pedido->folio).'. Motivo: '.$motivo,
                [],
                $usuarioId,
                true,
                ['url' => '/control-pedidos?tab=RECHAZADAS&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $pedido;
        });
    }
}
