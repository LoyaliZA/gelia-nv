<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Solicitud comercial, autorización y hechos físicos conservan etapas independientes. */
final class MovimientosTiendaService
{
    public function aplica(PedidoBma $pedido): bool
    {
        return ($pedido->solicitud_preparacion_json['modalidad_entrega'] ?? null) === 'tienda';
    }

    public function enCustodia(PedidoBma $pedido): bool
    {
        return $pedido->estatus_sucursal === EstatusSucursalPedidoBma::EN_CUSTODIA
            && ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)
                ->where('sucursal_id', $pedido->sucursal_destino_id)->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)->where('entrega_bloqueada', false)->exists();
    }

    public function tieneCustodiaDestino(int $sucursalId): bool
    {
        return Almacen::query()->with('tipoAlmacen')->where('activo', true)->where('sucursal_id', $sucursalId)->get()
            ->contains(fn ($almacen) => \App\Support\ControlPedidos\AlmacenPdv::esAlmacen($almacen));
    }

    public function resumen(PedidoBma $pedido, User $actor): array
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        $traslado = $json['segundo_traslado'] ?? null;
        $opera = $actor->can('control_pedidos.tienda.trasladar') || $actor->can('control_pedidos.tienda.responder');
        $sucursales = $actor->idsSucursalesOperables();
        $origen = (int) ($traslado['sucursal_origen_id'] ?? $pedido->sucursal_destino_id);
        $destino = (int) ($traslado['sucursal_destino_id'] ?? $pedido->sucursal_destino_id);
        $custodiaDestino = $this->tieneCustodiaDestino($destino);
        $visible = VisibilidadPedidoBma::puedeConsultar($actor, $pedido);
        abort_unless($visible || ($opera && ($sucursales->contains($origen) || $sucursales->contains($destino))), 403);
        $activo = $this->aplica($pedido) && ! $pedido->cancelado_at && $pedido->estatus?->fase_ciclo !== 'ENTREGADO';
        $pendiente = ($traslado['estado'] ?? '') === 'pendiente';
        $autorizada = ($traslado['estado'] ?? '') === 'autorizada' && ! ($traslado['recepcion_confirmada'] ?? false);
        $modalidad = $json['cambio_modalidad_tienda'] ?? null;
        $libre = ! $pendiente && ! $autorizada && ($modalidad['estado'] ?? '') !== 'pendiente_conexion';
        $solicitar = $activo && $libre && $this->enCustodia($pedido)
            && (int) $pedido->vendedor_id === (int) $actor->id && $actor->can('control_pedidos.crear')
            && ! $pedido->motivoBloqueoEntregaTienda();

        return [
            'aplica' => $this->aplica($pedido),
            'version' => (int) ($json['movimientos_tienda_version'] ?? 0),
            'sucursal_actual' => Sucursal::find($origen)?->nombre,
            'sucursal_actual_id' => ($traslado['recepcion_confirmada'] ?? false) ? $destino : $origen,
            'sucursal_destino' => Sucursal::find($destino)?->nombre,
            'traslado' => $traslado,
            'modalidad' => $modalidad,
            'puede_solicitar' => $solicitar,
            'puede_resolver' => $activo && $pendiente && $visible && $actor->can(SolicitarSegundoTrasladoTiendaService::PERMISO),
            'bloqueo_destino' => ! $custodiaDestino ? 'La sucursal destino necesita un almacén PDV activo para recibir y custodiar el pedido. Coordine su configuración antes de la salida.' : null,
            'puede_salida' => $custodiaDestino && $activo && $autorizada && ($traslado['movimiento_estado'] ?? '') !== 'en_camino' && $opera && $sucursales->contains($origen),
            'puede_recibir' => $activo && $autorizada && ($traslado['movimiento_estado'] ?? '') === 'en_camino' && $opera && $sucursales->contains($destino),
            'sucursales' => Sucursal::query()->where('activo', true)->whereKeyNot($pedido->sucursal_destino_id)->get(['id', 'nombre']),
            'almacenes' => Almacen::query()->with('tipoAlmacen')->where('activo', true)->where('sucursal_id', $destino)->get()->filter(fn ($almacen) => \App\Support\ControlPedidos\AlmacenPdv::esAlmacen($almacen))->map(fn ($almacen) => ['id' => $almacen->id, 'nombre' => $almacen->nombre])->values(),
            'evidencias' => $pedido->documentos()->where('relacion_tipo', 'traslado_peticion_cliente')->whereIn('id', $traslado['documentos_ids'] ?? [])->get(),
        ];
    }

    public function ejecutar(PedidoBma $pedido, User $actor, string $accion, array $datos): array
    {
        return DB::transaction(function () use ($pedido, $actor, $accion, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $resumen = $this->resumen($pedido, $actor);
            if ((int) $datos['version'] !== $resumen['version']) {
                throw ValidationException::withMessages(['version' => 'El pedido cambió. Actualice el seguimiento antes de continuar.']);
            }
            $capacidad = match ($accion) {
                'solicitar', 'modalidad' => 'puede_solicitar',
                'autorizar', 'rechazar' => 'puede_resolver',
                'salida' => 'puede_salida',
                'recepcion' => 'puede_recibir',
                default => '',
            };
            if (! $resumen['aplica'] || ! ($resumen[$capacidad] ?? false)) {
                throw ValidationException::withMessages(['operacion' => 'La operación espera la etapa anterior y al responsable autorizado de esta sucursal.']);
            }
            if ($accion === 'solicitar') {
                app(SolicitarSegundoTrasladoTiendaService::class)->ejecutar($pedido, $actor, (int) $datos['sucursal_destino_id'], $datos);
            } elseif (in_array($accion, ['autorizar', 'rechazar'], true)) {
                app(ResolverSegundoTrasladoTiendaService::class)->ejecutar($pedido, $actor, $accion, $datos['recorrido'] ?? '');
            } elseif ($accion === 'salida') {
                app(EjecutarSegundoTrasladoTiendaService::class)->salir($pedido, $actor);
            } elseif ($accion === 'recepcion') {
                app(EjecutarSegundoTrasladoTiendaService::class)->recibir($pedido, $actor, $datos);
            } else {
                $json = $pedido->solicitud_preparacion_json;
                $json['cambio_modalidad_tienda'] = ['estado' => 'pendiente_conexion', 'destino' => $datos['modalidad'],
                    'motivo' => $datos['motivo'], 'solicitada_por_id' => $actor->id, 'solicitada_at' => now()->toIso8601String(),
                    'sucursal_custodia_id' => $pedido->sucursal_destino_id];
                $pedido->update(['solicitud_preparacion_json' => $json]);
                app(RegistrarHistorialPedidoService::class)->ejecutar($pedido->id, $actor->id,
                    $pedido->catalogo_estatus_pedido_id, $pedido->catalogo_estatus_pedido_id,
                    'Solicitud de cambio a envío '.$datos['modalidad'].'. '.$datos['motivo'].'. Pendiente de conexión; se conserva la custodia y el pago.',
                    'solicitud_cambio_modalidad_tienda');
            }
            $pedido->refresh();
            $json = $pedido->solicitud_preparacion_json;
            $json['movimientos_tienda_version'] = $resumen['version'] + 1;
            $pedido->update(['solicitud_preparacion_json' => $json]);

            return $this->resumen($pedido->fresh(), $actor);
        });
    }
}
