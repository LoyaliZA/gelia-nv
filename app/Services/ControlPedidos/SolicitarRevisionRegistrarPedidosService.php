<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ContextoDepartamentoPedido;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\RegistrarPedidos\OrigenCapturaPedido;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Envía la revisión de Tienda a Preparación Tienda o a Control Pedidos, sin apartar ni confirmar pago. */
class SolicitarRevisionRegistrarPedidosService
{
    public function __construct(
        private DestinoRevisionRegistrarPedidos $destinos,
        private FusionarSolicitudPreparacionPedido $fusionar,
        private AsegurarCumplimientoFisicoService $cumplimiento,
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    /**
     * @param  array{revision_esperada?: int, base_captura?: string}  $base
     * @return array{area: string, etiqueta: string, espera: string, almacen: ?string, idempotente: bool, pedido: PedidoBma}
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, string $area, ?int $almacenId, array $base): array
    {
        if (! in_array($area, [DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA, DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS], true)) {
            throw ValidationException::withMessages(['area_revision' => DestinoRevisionRegistrarPedidos::MENSAJE_AREA]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $area, $almacenId, $base) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing('estatus');

            $previo = $this->yaEnviado($pedido, $area, $almacenId);
            if ($previo !== null) {
                return $previo;
            }

            RevisionCapturaPedido::comprobar($pedido, $base);
            $this->validarCaptura($pedido, $area);

            $almacen = null;
            if ($area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA) {
                $resuelto = DestinoRevisionRegistrarPedidos::resolverAlmacen(
                    $this->destinos->almacenesTienda()->pluck('id')->all(),
                    $almacenId
                );
                if (! $resuelto['definido'] || $resuelto['id'] === null) {
                    throw ValidationException::withMessages(['almacen_id' => $resuelto['motivo'] ?? DestinoRevisionRegistrarPedidos::MENSAJE_ALMACEN]);
                }
                $almacen = Almacen::query()->findOrFail($resuelto['id']);
                $this->crearTareaTienda($pedido, $usuario, $almacen);
            } else {
                $this->marcarConsultaCedis($pedido, $usuario);
            }

            $this->guardarDestino($pedido, $area, $almacen?->id);
            if (($pedido->solicitud_preparacion_json['caso_tienda'] ?? '') === 'mismo_dia_pdv') {
                $json = $pedido->solicitud_preparacion_json ?? [];
                $json['tienda_md_solicitud'] ??= ['fecha' => now(app(PreparacionTiendaConfig::class)->zonaHoraria())->toDateString(), 'registrada_at' => now()->toIso8601String(), 'por_id' => $usuario->id];
                $pedido->update(['solicitud_preparacion_json' => $json]);
            }
            $pedido = $pedido->fresh(['estatus', 'cliente']);
            $nombre = $almacen?->nombre;

            return [
                'area' => $area,
                'etiqueta' => DestinoRevisionRegistrarPedidos::etiqueta($area),
                'espera' => DestinoRevisionRegistrarPedidos::espera($area, $nombre),
                'almacen' => $nombre,
                'idempotente' => false,
                'pedido' => $pedido,
            ];
        }, 3);
    }

    /**
     * @return array{area: string, etiqueta: string, espera: string, almacen: ?string, idempotente: bool, pedido: PedidoBma}|null
     */
    private function yaEnviado(PedidoBma $pedido, string $area, ?int $almacenId): ?array
    {
        $guardada = DestinoRevisionRegistrarPedidos::areaGuardada($pedido);
        $abiertas = InstruccionEntrega::abiertas((int) $pedido->id);

        if ($abiertas->isNotEmpty()) {
            if ($area !== DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA || $guardada !== DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA) {
                throw ValidationException::withMessages([
                    'area_revision' => 'Este pedido ya tiene una solicitud en curso.',
                ]);
            }
            $tarea = $abiertas->first();
            if ($almacenId !== null && (int) $tarea->almacen_id !== $almacenId) {
                throw ValidationException::withMessages(['almacen_id' => DestinoRevisionRegistrarPedidos::MENSAJE_DESTINO_FIJO]);
            }
            $tarea->loadMissing('almacen');

            return $this->respuesta($pedido, DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA, $tarea->almacen?->nombre, true);
        }

        if ($guardada === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS
            && $pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE) {
            if ($area !== DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS) {
                throw ValidationException::withMessages(['area_revision' => DestinoRevisionRegistrarPedidos::MENSAJE_DESTINO_FIJO]);
            }

            return $this->respuesta($pedido, DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS, null, true);
        }

        return null;
    }

    private function validarCaptura(PedidoBma $pedido, string $area): void
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        if (($json['modalidad_entrega'] ?? '') !== 'tienda') {
            throw ValidationException::withMessages(['modalidad_entrega' => 'La solicitud corresponde a la modalidad Tienda.']);
        }
        if (! $pedido->cliente_id) {
            throw ValidationException::withMessages(['cliente_id' => 'Indica el número de cliente.']);
        }
        if (! ($pedido->departamento_operacion_id ?: ContextoDepartamentoPedido::dePedido($pedido))) {
            throw ValidationException::withMessages(['departamento_operacion_id' => 'Indica el departamento.']);
        }
        if (blank($pedido->folio_remision)) {
            throw ValidationException::withMessages(['folio_remision' => 'Indica el folio del pedido.']);
        }
        if (! $pedido->tieneSoporteSolicitud()) {
            throw ValidationException::withMessages(['pdf' => 'Adjunta el PDF o la imagen del pedido.']);
        }
        if (($json['caso_tienda'] ?? '') === 'mismo_dia_pdv') {
            if ($area !== DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS) {
                throw ValidationException::withMessages(['caso_tienda' => 'TIENDA MD solicita disponibilidad y traslado desde CEDIS. El caso de piezas ya disponibles en PDV queda pendiente de definición.']);
            }
            if (! app(ConfiguracionOperativaTienda::class)->diaMd(now(app(PreparacionTiendaConfig::class)->zonaHoraria()))) {
                throw ValidationException::withMessages(['caso_tienda' => 'Mismo día no está habilitado para esta fecha.']);
            }
            app(ValidarSucursalDestinoPedidoBma::class)->ejecutar($pedido, $pedido->sucursal_destino_id, 'RECOGE_TIENDA_TRANSFERENCIA', true);
        }
        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_BORRADOR) {
            throw ValidationException::withMessages(['estado' => 'Este pedido ya no está en captura.']);
        }
        if ($area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS && $pedido->almacen_id) {
            $salida = Almacen::query()->find($pedido->almacen_id);
            if ($salida && \App\Support\ControlPedidos\AlmacenPdv::esAlmacen($salida)) {
                throw ValidationException::withMessages([
                    'area_revision' => 'La salida ya es un almacén de tienda. La separación en CEDIS no queda definida.',
                ]);
            }
        }
    }

    private function crearTareaTienda(PedidoBma $pedido, User $usuario, Almacen $almacen): PedidoBmaTareaPreparacion
    {
        $modalidad = CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA)
            ->where('activo', true)
            ->first();
        if (! $modalidad) {
            throw ValidationException::withMessages([
                'modalidad' => 'No hay una modalidad de apartado en tienda disponible para crear la solicitud.',
            ]);
        }

        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus de revisión pendiente.');
        }

        MaquinaEstadosPedidoBma::assertTransicion(
            $pedido->estatus?->fase_ciclo,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
        );

        $clave = 'registrar-revision:'.$pedido->id.':'.DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA;
        $tarea = PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $almacen->id,
            'area_responsable_codigo' => 'TIENDA',
            'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            'solicitada_por_id' => $usuario->id,
            'solicitada_at' => now(),
            'fecha_limite' => null,
            'observaciones_solicitud' => 'Pendiente de revisión del archivo. Sin apartado ni disponibilidad confirmada.',
            'idempotencia_clave' => $clave,
            'requiere_traslado_cedis' => false,
        ]);
        $this->cumplimiento->ejecutar($tarea);

        $estatusAnterior = $pedido->estatus;
        $cambios = [
            'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            'almacen_id' => $almacen->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'pesaje_solicitado_at' => now(),
            'pesaje_respondido_at' => null,
            'pesaje_respondido_por_id' => null,
            'consulta_cerrada_at' => null,
            'consulta_cerrada_por_id' => null,
            'consulta_actualizacion_pendiente' => false,
            'prioridad_md' => false,
        ];
        if ($almacen->sucursal_id && ! $pedido->sucursal_destino_id) {
            $cambios['sucursal_destino_id'] = $almacen->sucursal_id;
        }
        $this->origenUnico($pedido, $cambios);
        $pedido->update($cambios);

        $this->historial->ejecutar(
            $pedido->id,
            $usuario->id,
            $estatusAnterior->id,
            $estatusNuevo->id,
            'Solicitud de apartado enviada a Preparación Tienda. Pendiente de revisión.',
            AccionesHistorialPedidoBma::SOLICITUD_PREPARACION_TIENDA
        );
        $this->notificar->ejecutar(
            $pedido->fresh(['cliente', 'vendedor']),
            'pedido_consulta_nueva',
            'Nueva solicitud de apartado en Tienda, pendiente de revisión.',
            ['control_pedidos.tienda.ver'],
            $usuario->id,
            false,
            ['url' => '/control-pedidos/tienda?tarea='.$tarea->id]
        );

        return $tarea;
    }

    private function marcarConsultaCedis(PedidoBma $pedido, User $usuario): void
    {
        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus de revisión pendiente.');
        }

        MaquinaEstadosPedidoBma::assertTransicion(
            $pedido->estatus?->fase_ciclo,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
        );

        $estatusAnterior = $pedido->estatus;
        $cambios = [
            'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'pesaje_solicitado_at' => now(),
            'pesaje_respondido_at' => null,
            'pesaje_respondido_por_id' => null,
            'consulta_cerrada_at' => null,
            'consulta_cerrada_por_id' => null,
            'consulta_actualizacion_pendiente' => false,
            'prioridad_md' => false,
        ];
        $this->origenUnico($pedido, $cambios);
        $pedido->update($cambios);

        $this->historial->ejecutar(
            $pedido->id,
            $usuario->id,
            $estatusAnterior->id,
            $estatusNuevo->id,
            'Consulta de disponibilidad enviada a Control Pedidos. Pendiente de revisión. No hay traslado.',
            AccionesHistorialPedidoBma::SOLICITUD_PESAJE
        );
        $this->notificar->ejecutar(
            $pedido->fresh(['cliente', 'vendedor']),
            'pedido_consulta_nueva',
            'Nueva consulta de disponibilidad en CEDIS, pendiente de revisión.',
            ['control_pedidos.cedis'],
            $usuario->id,
            false,
            ['url' => '/control-pedidos/cedis?tab=PENDIENTES_PESAJE']
        );
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function origenUnico(PedidoBma $pedido, array &$cambios): void
    {
        if ($pedido->origen_id) {
            return;
        }
        $id = OrigenCapturaPedido::idTiendaUnico(
            CatalogoOrigenPedido::query()->where('activo', true)->get(['id', 'requiere_logistica', 'activo'])
        );
        if ($id) {
            $cambios['origen_id'] = $id;
        }
    }

    private function guardarDestino(PedidoBma $pedido, string $area, ?int $almacenId): void
    {
        $pedido->update([
            'solicitud_preparacion_json' => $this->fusionar->ejecutar(
                is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : null,
                [
                    'modalidad_entrega' => 'tienda',
                    'area_revision' => $area,
                    'almacen_revision_id' => $almacenId,
                ]
            ),
        ]);
    }

    /**
     * @return array{area: string, etiqueta: string, espera: string, almacen: ?string, idempotente: bool, pedido: PedidoBma}
     */
    private function respuesta(PedidoBma $pedido, string $area, ?string $almacen, bool $idempotente): array
    {
        return [
            'area' => $area,
            'etiqueta' => DestinoRevisionRegistrarPedidos::etiqueta($area),
            'espera' => DestinoRevisionRegistrarPedidos::espera($area, $almacen),
            'almacen' => $almacen,
            'idempotente' => $idempotente,
            'pedido' => $pedido->fresh(['estatus', 'cliente']),
        ];
    }
}
