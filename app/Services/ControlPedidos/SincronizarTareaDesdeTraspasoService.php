<?php

namespace App\Services\ControlPedidos;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza el estado de la tarea de preparación cuando CEDIS confirma o rechaza el traspaso.
 * Idempotente: no duplica historial si ya está en el estado destino.
 */
class SincronizarTareaDesdeTraspasoService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function desdeConfirmacion(SolicitudTraspaso $solicitud, User $usuario): ?PedidoBmaTareaPreparacion
    {
        return $this->aplicar($solicitud, $usuario, true, null);
    }

    public function desdeRechazo(SolicitudTraspaso $solicitud, User $usuario, ?string $motivo = null): ?PedidoBmaTareaPreparacion
    {
        return $this->aplicar($solicitud, $usuario, false, $motivo);
    }

    /**
     * Reconcilia una solicitud ya verificada/incorrecta cuyo estado de tarea quedó atrás.
     */
    public function reconciliar(SolicitudTraspaso $solicitud, ?User $actor = null): ?PedidoBmaTareaPreparacion
    {
        if (! $solicitud->tarea_preparacion_id) {
            return null;
        }

        $idVerificada = CatalogoEstadoSolicitud::idDe('Verificada');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $estadoId = (int) $solicitud->catalogo_estado_solicitud_id;

        $actor ??= User::query()->find($solicitud->respondida_por_id)
            ?? User::query()->find($solicitud->vendedor_id);

        if (! $actor) {
            return null;
        }

        if ($idVerificada && $estadoId === $idVerificada) {
            return $this->desdeConfirmacion($solicitud, $actor);
        }

        if ($idIncorrecta && $estadoId === $idIncorrecta) {
            return $this->desdeRechazo(
                $solicitud,
                $actor,
                $solicitud->motivo_respuesta ?: $solicitud->motivo_incorrecta
            );
        }

        return null;
    }

    private function aplicar(
        SolicitudTraspaso $solicitud,
        User $usuario,
        bool $recibida,
        ?string $motivo,
    ): ?PedidoBmaTareaPreparacion {
        $tareaId = $solicitud->tarea_preparacion_id;
        if (! $tareaId) {
            return null;
        }

        return DB::transaction(function () use ($solicitud, $usuario, $recibida, $motivo, $tareaId) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->find($tareaId);
            if (! $tarea) {
                return null;
            }

            $destino = $recibida
                ? PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS
                : PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS;

            if ($tarea->estado === $destino
                || ($recibida && $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS)
            ) {
                return $tarea;
            }

            if (! in_array($tarea->estado, [
                PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
                PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
                PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS,
            ], true)) {
                return $tarea;
            }

            // Si ya pasó a incidencia tras rechazo, no reaplicar.
            if (! $recibida && $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA) {
                return $tarea;
            }

            if ($recibida) {
                $tarea->update([
                    'recibida_cedis_por_id' => $usuario->id,
                    'recibida_cedis_at' => now(),
                    'motivo_rechazo_cedis' => null,
                ]);
                $tarea->pedido?->update([
                    'pesaje_respondido_at' => now(),
                    'pesaje_respondido_por_id' => $usuario->id,
                    'estatus_envio' => \App\Models\ControlPedidos\PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
                    'consulta_actualizacion_pendiente' => false,
                ]);
            } else {
                $tarea->update([
                    'motivo_rechazo_cedis' => $motivo,
                ]);
                $tarea->pedido?->update(['consulta_actualizacion_pendiente' => true]);
            }

            $tarea = $this->transicionService->ejecutar(
                $tarea,
                $destino,
                $usuario->id,
                $recibida ? 'recepcion_cedis' : 'rechazo_cedis',
                $recibida ? 'CEDIS confirmó recepción del traspaso.' : ('CEDIS rechazó el traspaso: '.($motivo ?: 'sin motivo')),
                ['solicitud_traspaso_id' => $solicitud->id, 'motivo' => $motivo],
                null,
                $usuario,
                true
            );

            if (! $recibida) {
                $tarea = $this->transicionService->ejecutar(
                    $tarea,
                    PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
                    $usuario->id,
                    'incidencia_por_rechazo_cedis',
                    'Rechazo CEDIS convertido en incidencia para Ventas.',
                    null,
                    null,
                    $usuario,
                    true
                );
            }

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor', 'estatus'])->first();
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                $recibida
                    ? 'CEDIS recibió mercancía proveniente de Tienda.'
                    : 'CEDIS rechazó mercancía proveniente de Tienda.',
                $recibida
                    ? AccionesHistorialPedidoBma::TRASLADO_PREPARACION_RECIBIDO
                    : AccionesHistorialPedidoBma::TRASLADO_PREPARACION_RECHAZADO
            );

            $this->notificarService->ejecutar(
                $pedido,
                $recibida ? 'pedido_preparacion_tienda_recibida_cedis' : 'pedido_preparacion_tienda_rechazada_cedis',
                $recibida
                    ? 'CEDIS recibió la mercancía de Tienda. Puede cerrar la consulta.'
                    : ('CEDIS rechazó el traslado: '.($motivo ?: 'revise la incidencia')),
                $recibida ? [] : ['control_pedidos.tienda.ver'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $tarea->fresh(['modalidad', 'solicitudTraspaso', 'productos']);
        });
    }
}
