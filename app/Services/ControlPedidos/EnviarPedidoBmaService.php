<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\Direcciones\CrearSnapshotDireccionPedido;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EnviarPedidoBmaService
{
    use ValidacionCamposPedidoBma;

    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private CrearSnapshotDireccionPedido $crearSnapshot,
        private NotificarPedidoBmaService $notificarService,
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
        private RegistrarPagoPedidoBmaService $pagosService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Solo se pueden enviar pedidos en borrador o rechazados.');
        }

        $actor = User::find($usuarioId);
        if (! $actor || ! VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido)) {
            throw new \RuntimeException('Solo la vendedora que creó el pedido puede reenviarlo o corregir sus errores.');
        }

        $pedido->assertSinExistenciaAtendida();
        $this->validarCamposRequeridos($pedido);
        $this->pagosService->assertCubiertoParaEnviar($pedido);
        $this->pagosService->generarExcedenteSiAplica($pedido, $usuarioId);

        if (config('control_pedidos.direcciones_normalizadas')) {
            $pedido->loadMissing('origen');
            if (
                $pedido->origen?->requiere_logistica
                && ! $pedido->cliente_proporciona_guia
                && ! $pedido->cliente_direccion_id
            ) {
                throw new \InvalidArgumentException('Debe seleccionar una dirección de envío verificada.');
            }
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatusAnterior = $pedido->estatus;
            MaquinaEstadosPedidoBma::assertTransicion(
                $estatusAnterior?->fase_ciclo,
                CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR
            );
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)
                ?? CatalogoEstatusPedido::porCodigo('AZUL_1');

            if (!$estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus PENDIENTE_AUXILIAR.');
            }

            if (config('control_pedidos.direcciones_normalizadas') || $pedido->cliente_direccion_id) {
                $this->crearSnapshot->ejecutar($pedido, $usuarioId);
                $pedido->refresh();
            }

            $restantes = $this->colaErroresService->quitarDueno(
                $pedido,
                CamposIncorrectosPedidoBma::DUENO_VENDEDORA,
                $usuarioId,
                'Datos de vendedora corregidos al reenviar'
            );

            $attrsError = $restantes === []
                ? $this->colaErroresService->attrsColaVacia()
                : $this->colaErroresService->attrsColaPendiente($restantes);

            $pedido->update(array_merge([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'estatus_envio' => $this->resolverEstatusEnvioAlEnviar($pedido),
                'pago_validado_at' => null,
                'pago_validado_por_id' => null,
            ], $attrsError));

            $this->eliminarRemisiones($pedido);

            $comentario = $restantes === []
                ? 'Pedido enviado a revisión del auxiliar.'
                : 'Pedido enviado a revisión del auxiliar. Errores pendientes: '
                    .implode(', ', CamposIncorrectosPedidoBma::etiquetasDe($restantes));

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                $comentario,
                AccionesHistorialPedidoBma::ENVIO_AUXILIAR
            );

            $pedido = $pedido->fresh(['cliente', 'estatus', 'documentos', 'almacen', 'banco', 'direccionVigente', 'vendedor']);

            $q = urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id));

            // Enviar siempre reinicia remisión/pago: el auxiliar es el siguiente paso operativo.
            // La cola de guías se notifica al aprobar, no aquí.
            $mensaje = $restantes === []
                ? 'Nuevo pedido pendiente de auditoría'
                : 'Pedido pendiente de auditoría. Errores por resolver: '
                    .implode(', ', CamposIncorrectosPedidoBma::etiquetasDe($restantes));
            $tipo = CamposIncorrectosPedidoBma::camposDeDueno(
                $restantes,
                CamposIncorrectosPedidoBma::DUENO_AUXILIAR
            ) !== []
                ? 'pedido_error_remision'
                : 'pedido_pendiente_auxiliar';

            $this->notificarService->ejecutar(
                $pedido,
                $tipo,
                $mensaje,
                ['control_pedidos.auditar'],
                $usuarioId,
                false,
                [
                    'url' => '/control-pedidos/auditar?tab=PENDIENTES&q='.$q,
                    'campos_incorrectos' => $restantes,
                ]
            );

            return $pedido;
        });
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
