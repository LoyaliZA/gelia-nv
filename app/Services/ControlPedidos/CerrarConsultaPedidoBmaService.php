<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;

class CerrarConsultaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            return $this->cerrar($pedido, $usuarioId);
        });
    }

    private function cerrar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen', 'vendedor']);

        if ($pedido->esRecogeTiendaApartado() && ! $pedido->listaParaCerrarConsultaTienda()) {
            throw new \RuntimeException('Recoge en tienda no cierra consulta ni abre monto o pago.');
        }

        $actor = \App\Models\User::find($usuarioId);
        if (! $actor || ! VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido)) {
            throw new \RuntimeException('Solo la vendedora del pedido puede cerrar la consulta.');
        }

        if (! $pedido->puedeCerrarConsulta()) {
            throw new \RuntimeException(
                $pedido->motivoBloqueoCierreConsultaTienda()
                    ?: 'No se puede cerrar la consulta: se requiere respuesta vigente y que no haya ajustes pendientes.'
            );
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatus = $pedido->estatus;
            $yaCerrada = $pedido->consultaCerrada();
            $destino = $estatus;
            if ($estatus->fase_ciclo === \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE) {
                $destino = \App\Models\ControlPedidos\CatalogoEstatusPedido::porFase(\App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO);
                if (! $destino) throw new \RuntimeException('Falta configurar el estado de consulta aprobada.');
                \App\Support\ControlPedidos\MaquinaEstadosPedidoBma::assertTransicion($estatus->fase_ciclo, $destino->fase_ciclo);
            }
            $json = $pedido->solicitud_preparacion_json ?? [];
            if (\App\Support\ControlPedidos\ApartadoT01::aplica($pedido)) {
                $json['cobertura_tienda']['requiere_confirmacion_pedido'] = true;
                if (! $yaCerrada) unset($json['cobertura_tienda']['confirmacion_enviada_at'], $json['cobertura_tienda']['confirmacion_enviada_por_id']);
            }
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'catalogo_estatus_pedido_id' => $destino->id,
                'consulta_cerrada_at' => now(),
                'consulta_cerrada_por_id' => $usuarioId,
                'consulta_actualizacion_pendiente' => false,
                'estatus_envio' => $pedido->esConsultaDisponibilidadCedis() ? PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO : $pedido->estatus_envio,
            ]);

            $label = $pedido->consultaCedisModoRevisionMercancia() ? 'Consulta de mercancía' : 'Consulta de pesaje';
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $destino->id,
                "{$label} cerrada por Ventas (cliente confirma piezas).",
                AccionesHistorialPedidoBma::CIERRE_CONSULTA
            );

            if (! $yaCerrada && \App\Support\ControlPedidos\ApartadoT01::aplica($pedido)) {
                $tarea = \App\Support\ControlPedidos\InstruccionEntrega::abiertas((int) $pedido->id)->first();
                if ($tarea?->asignada_a_id) {
                    $auxiliar = \App\Models\User::find($tarea->asignada_a_id);
                    if ($auxiliar) \Illuminate\Support\Facades\DB::afterCommit(fn () => $auxiliar->notify(new \App\Notifications\AlertaPedidoBma(
                        $pedido, 'pedido_separacion_aprobada', 'Consulta aprobada. Espera la confirmación del pedido de Ventas.', ['url' => '/control-pedidos/tienda/tareas/'.$tarea->id]
                    )));
                }
            }
            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'origen',
            ]);
        });
    }
}
