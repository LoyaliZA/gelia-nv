<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\AlmacenPdv;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Autorizar no traslada. La salida y la recepción son hechos distintos. */
final class EjecutarSegundoTrasladoTiendaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function salir(PedidoBma $pedido, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.tienda.trasladar') && ! $usuario->can('control_pedidos.tienda.responder')) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien tiene la mercancía registra la salida.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $this->json($pedido);
            $solicitud = $this->autorizada($json);
            $this->assertOperable($pedido, $usuario, (int) ($solicitud['sucursal_origen_id'] ?? 0));
            if (trim($solicitud['recorrido'] ?? '') === '') {
                throw ValidationException::withMessages(['recorrido' => 'Gerencia debe definir el recorrido.']);
            }
            if (($solicitud['movimiento_estado'] ?? null) === 'en_camino') {
                return $pedido;
            }
            if (($solicitud['recepcion_confirmada'] ?? false) === true) {
                throw ValidationException::withMessages(['traslado' => 'La recepción ya está confirmada.']);
            }

            if (! ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->where('sucursal_id', $solicitud['sucursal_origen_id'])->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)->exists()) {
                throw ValidationException::withMessages(['custodia' => 'La mercancía debe estar en custodia en origen antes de salir.']);
            }
            if (! app(MovimientosTiendaService::class)->tieneCustodiaDestino((int) $solicitud['sucursal_destino_id'])) {
                throw ValidationException::withMessages(['custodia' => 'Configure un almacén PDV activo de custodia en destino antes de confirmar la salida.']);
            }
            if (ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->where('sucursal_id', $solicitud['sucursal_destino_id'])->exists()) {
                throw ValidationException::withMessages(['custodia' => 'El destino ya tiene un registro de resguardo para este pedido. Revise su conciliación antes de salir.']);
            }
            $solicitud['movimiento_estado'] = 'en_camino';
            $solicitud['salida_at'] = now()->toIso8601String();
            $solicitud['salida_por_id'] = $usuario->id;
            $solicitud['recepcion_confirmada'] = false;
            $json['segundo_traslado'] = $solicitud;
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'estatus_sucursal' => EstatusSucursalPedidoBma::EN_TRANSITO,
            ]);
            ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('sucursal_id', $solicitud['sucursal_origen_id'])
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->update(['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, 'sucursal_id' => $solicitud['sucursal_destino_id'], 'almacen_id' => null, 'custodia_confirmada_at' => null, 'version' => DB::raw('version + 1')]);

            $this->anotar($pedido, $usuario, 'Salió el segundo traslado. Falta la recepción en destino. El cliente no recoge en CEDIS.');
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_segundo_traslado_salida',
                'La mercancía va hacia la sucursal autorizada. Falta confirmar la recepción.',
                ['control_pedidos.tienda.ver'],
                $usuario->id,
                true
            );

            return $pedido->fresh();
        });
    }

    public function recibir(PedidoBma $pedido, User $usuario, array $datos = []): PedidoBma
    {
        if (! $usuario->can('control_pedidos.tienda.responder') && ! $usuario->can('control_pedidos.tienda.trasladar')) {
            throw ValidationException::withMessages([
                'permiso' => 'La sucursal de destino confirma la recepción.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $this->json($pedido);
            $solicitud = $this->autorizada($json);
            $this->assertOperable($pedido, $usuario, (int) $solicitud['sucursal_destino_id']);
            if (($solicitud['movimiento_estado'] ?? null) !== 'en_camino') {
                throw ValidationException::withMessages([
                    'traslado' => 'Primero registre la salida. La autorización no mueve la mercancía.',
                ]);
            }

            $destinoId = (int) $solicitud['sucursal_destino_id'];
            $almacen = Almacen::query()->where('activo', true)->where('sucursal_id', $destinoId)->find($datos['almacen_id'] ?? 0);
            if (empty($datos['bolsas_verificadas']) || ! $almacen || ! AlmacenPdv::esAlmacen($almacen) || trim($datos['ubicacion'] ?? '') === '') {
                throw ValidationException::withMessages(['custodia' => 'Verifique las bolsas e identifique el almacén PDV y la ubicación de custodia en destino.']);
            }
            $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)
                ->where('sucursal_id', $destinoId)->where('estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION)->lockForUpdate()->first();
            if (! $resguardo) {
                throw ValidationException::withMessages(['custodia' => 'No se encontró la custodia que salió de origen.']);
            }
            $ubicacion = trim($datos['ubicacion']);
            $json['transferencia_tienda']['ubicacion'] = $ubicacion;
            $json['transferencia_tienda']['almacen_custodia_id'] = $almacen->id;
            $solicitud['almacen_custodia_id'] = $almacen->id;
            $solicitud['ubicacion'] = $ubicacion;

            $solicitud['movimiento_estado'] = 'recibido';
            $solicitud['recepcion_confirmada'] = true;
            $solicitud['recepcion_at'] = now()->toIso8601String();
            $solicitud['recepcion_por_id'] = $usuario->id;
            $json['segundo_traslado'] = $solicitud;
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA,
                'sucursal_destino_id' => $destinoId,
            ]);
            $resguardo->update([
                'sucursal_id' => $destinoId, 'almacen_id' => $almacen->id,
                'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA, 'recepcion_fisica_at' => now(),
                'custodia_confirmada_at' => now(), 'version' => $resguardo->version + 1,
                'snapshot_json' => array_merge($resguardo->snapshot_json ?? [], ['ubicacion_t02' => $ubicacion]),
            ]);
            $resguardo->bultos()->update(['estado' => ResguardoPdvBulto::ESTADO_EN_CUSTODIA,
                'recepcion_at' => now(), 'recepcion_por_id' => $usuario->id, 'custodia_at' => now(), 'custodia_por_id' => $usuario->id]);

            $this->anotar($pedido, $usuario, 'La sucursal de destino confirmó la recepción del segundo traslado.');
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_segundo_traslado_recibido',
                'La mercancía llegó a la sucursal de destino y quedó en custodia.',
                ['control_pedidos.crear'],
                $usuario->id,
                true
            );

            return $pedido->fresh();
        });
    }

    private function assertOperable(PedidoBma $pedido, User $usuario, int $sucursalId): void
    {
        abort_unless($usuario->idsSucursalesOperables()->contains($sucursalId), 403);
        if ($pedido->cancelado_at || $pedido->estatus?->fase_ciclo === 'ENTREGADO') {
            throw ValidationException::withMessages(['traslado' => 'El pedido ya está cerrado.']);
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function autorizada(array $json): array
    {
        $solicitud = is_array($json['segundo_traslado'] ?? null) ? $json['segundo_traslado'] : [];
        if (($solicitud['estado'] ?? null) !== 'autorizada') {
            throw ValidationException::withMessages([
                'traslado' => 'Gerencia todavía no autoriza el segundo traslado.',
            ]);
        }

        return $solicitud;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(PedidoBma $pedido): array
    {
        return is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
    }

    private function anotar(PedidoBma $pedido, User $usuario, string $texto): void
    {
        $estatusId = $pedido->catalogo_estatus_pedido_id;
        if ($estatusId) {
            $this->historial->ejecutar(
                $pedido->id,
                $usuario->id,
                $estatusId,
                $estatusId,
                $texto,
                AccionesHistorialPedidoBma::CAMBIO_SUCURSAL_DESTINO
            );
        }
    }
}
