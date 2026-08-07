<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class AprobarPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
        private SincronizarAplicacionesPedidoSafService $safPedido,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se pueden aprobar pedidos pendientes de revisión.');
        }

        if (!$pedido->tienePagoValidado()) {
            throw new \RuntimeException('Debe validar el pago antes de aprobar.');
        }

        if (!$pedido->tieneRemision()) {
            throw new \RuntimeException('Debe adjuntar la remisión PDF antes de aprobar.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $this->safPedido->aplicarReservasPedido($pedido, $usuarioId);

            $estatusAnterior = $pedido->estatus;

            $restantes = $this->colaErroresService->quitarDueno(
                $pedido,
                CamposIncorrectosPedidoBma::DUENO_AUXILIAR,
                $usuarioId,
                'Remisión / datos de auxiliar corregidos al aprobar'
            );

            $siguiente = CamposIncorrectosPedidoBma::duenoActivo($restantes);

            if ($siguiente !== null) {
                $faseDestino = $this->colaErroresService->faseParaDuenoPendiente($pedido, $siguiente);
                $estatusNuevo = CatalogoEstatusPedido::porFase($faseDestino);
                if (! $estatusNuevo) {
                    throw new \RuntimeException("No se encontró el estatus {$faseDestino}.");
                }

                $pedido->update(array_merge([
                    'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                ], $this->colaErroresService->attrsColaPendiente($restantes)));

                $etiqueta = CamposIncorrectosPedidoBma::destinoPara($siguiente)['etiqueta'];
                $comentario = "Pedido aprobado; error pendiente de {$etiqueta}.";

                $this->historialService->registrarTransicion(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior,
                    $estatusNuevo,
                    $comentario,
                    AccionesHistorialPedidoBma::APROBACION
                );

                $pedido = $pedido->fresh([
                    'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                    'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                    'direccionVigente', 'vendedor',
                ]);

                $this->colaErroresService->notificarSiguienteSiAplica(
                    $pedido,
                    $restantes,
                    $usuarioId,
                    $faseDestino
                );

                return $pedido;
            }

            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS)
                ?? CatalogoEstatusPedido::porCodigo('AMARILLO');

            if (!$estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus EN_CEDIS.');
            }

            $pedido->update(array_merge([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            ], $this->colaErroresService->attrsColaVacia()));

            $comentario = $pedido->es_resguardo
                ? 'Pedido validado en resguardo. Visible en CEDIS; empaque bloqueado hasta liberar resguardo.'
                : 'Pedido aprobado y enviado a Registro General.';

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                $comentario,
                AccionesHistorialPedidoBma::APROBACION
            );

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                'direccionVigente', 'vendedor',
            ]);

            $q = urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id));
            $this->notificarService->ejecutar(
                $pedido,
                'pedido_aprobado',
                'Pedido aprobado y enviado a CEDIS',
                ['control_pedidos.cedis'],
                $usuarioId,
                true,
                ['url' => '/control-pedidos/cedis?tab=EMPACADOS&q='.$q]
            );

            return $pedido;
        });
    }
}
