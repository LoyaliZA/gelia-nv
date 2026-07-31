<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Support\Facades\DB;

class AprobarPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
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
            $estatusAnterior = $pedido->estatus;

            $restantes = $this->colaErroresService->quitarDueno(
                $pedido,
                CamposIncorrectosPedidoBma::DUENO_AUXILIAR
            );

            $hayGuiaPendiente = CamposIncorrectosPedidoBma::duenoActivo($restantes)
                === CamposIncorrectosPedidoBma::DUENO_GUIAS;

            if ($hayGuiaPendiente) {
                $faseDestino = $this->colaErroresService->faseParaGuiasPendientes($pedido);
                $estatusNuevo = CatalogoEstatusPedido::porFase($faseDestino);
                if (! $estatusNuevo) {
                    throw new \RuntimeException("No se encontró el estatus {$faseDestino}.");
                }

                $pedido->update(array_merge([
                    'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                ], $this->colaErroresService->attrsColaPendiente($restantes)));

                $comentario = $faseDestino === CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA
                    ? 'Pedido aprobado; error de guía pendiente — enviado a corrección de guía.'
                    : 'Pedido aprobado y enviado a CEDIS; error de guía pendiente de corrección.';

                $this->historialService->registrarTransicion(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior,
                    $estatusNuevo,
                    $comentario
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
                $comentario
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
