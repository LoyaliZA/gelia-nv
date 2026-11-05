<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Deja pendiente el segundo traslado. No mueve mercancía ni confirma recepción. */
final class SolicitarSegundoTrasladoTiendaService
{
    public const PERMISO = 'control_pedidos.tienda.autorizar_segundo_traslado';

    public function __construct(
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, int $sucursalDestinoId, array $datos = []): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') && ! $usuario->can('control_pedidos.editar')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para solicitar el cambio de sucursal.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $sucursalDestinoId, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! InstruccionEntrega::mercanciaYaSalio($pedido)) {
                throw ValidationException::withMessages([
                    'sucursal' => 'La mercancía aún no sale. El destino se actualiza en la instrucción vigente.',
                ]);
            }

            abort_unless((int) $pedido->vendedor_id === (int) $usuario->id, 403);
            if ($pedido->cancelado_at || $pedido->estatus?->fase_ciclo === 'ENTREGADO'
                || ! app(MovimientosTiendaService::class)->enCustodia($pedido)) {
                throw ValidationException::withMessages(['traslado' => 'Espere la recepción y custodia en la sucursal antes de solicitar otro traslado.']);
            }
            if ($sucursalDestinoId === (int) $pedido->sucursal_destino_id) {
                throw ValidationException::withMessages(['sucursal' => 'Seleccione una sucursal diferente de la actual.']);
            }
            $motivo = trim($datos['motivo'] ?? '');
            $errorCaptura = filter_var($datos['error_captura'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $archivos = $datos['evidencias'] ?? [];
            if ($motivo === '' || (! $errorCaptura && empty($archivos))) {
                throw ValidationException::withMessages(['evidencias' => 'Registre el motivo y adjunte la petición del cliente o notifique un error de captura.']);
            }
            if ($pedido->motivoBloqueoEntregaTienda()) {
                throw ValidationException::withMessages(['traslado' => $pedido->motivoBloqueoEntregaTienda()]);
            }

            $destino = Sucursal::query()->whereKey($sucursalDestinoId)->where('activo', true)->first();
            if (! $destino) {
                throw ValidationException::withMessages(['sucursal' => 'Elija una sucursal activa.']);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $vigente = is_array($json['segundo_traslado'] ?? null) ? $json['segundo_traslado'] : null;
            if (in_array($vigente['estado'] ?? '', ['pendiente', 'autorizada'], true) && ! ($vigente['recepcion_confirmada'] ?? false)) {
                throw ValidationException::withMessages(['sucursal' => 'Ya hay una solicitud de traslado en curso.']);
            }
            if (($json['cambio_modalidad_tienda']['estado'] ?? '') === 'pendiente_conexion') {
                throw ValidationException::withMessages(['modalidad' => 'Hay una solicitud de cambio de modalidad pendiente.']);
            }
            if ($vigente) {
                $json['historial_traslados_tienda'][] = $vigente;
            }
            $documentos = [];
            // Los documentos se asocian a la solicitud, sin alterar pagos ni remisiones.
            foreach ($archivos as $archivo) {
                $ruta = $archivo->store('pedidos_bma/'.$pedido->id.'/traslados', 'public');
                $doc = $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    'ruta_archivo' => $ruta, 'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(), 'tamano_bytes' => $archivo->getSize(),
                    'orden' => 1, 'relacion_tipo' => 'traslado_peticion_cliente', 'activo' => true,
                ]);
                $documentos[] = $doc->id;
            }

            $json['segundo_traslado'] = [
                'estado' => 'pendiente',
                'motivo' => $motivo,
                'error_captura' => $errorCaptura,
                'anotacion' => $errorCaptura ? 'Se notificó un error de captura' : null,
                'documentos_ids' => $documentos,
                'sucursal_origen_id' => $pedido->sucursal_destino_id,
                'sucursal_origen' => $pedido->sucursalDestino?->nombre,
                'sucursal_destino' => $destino->nombre,
                'sucursal_destino_id' => $destino->id,
                'solicitada_por_id' => $usuario->id,
                'solicitada_at' => now()->toIso8601String(),
                'resolucion' => null,
                'recorrido' => null,
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);

            $estatusId = $pedido->estatus?->id ?? $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    "Traslado solicitado hacia {$destino->nombre}. Motivo: {$motivo}. ".($errorCaptura ? 'Se notificó un error de captura. ' : '').'Pendiente de gerencia; la mercancía permanece en custodia.',
                    AccionesHistorialPedidoBma::CAMBIO_SUCURSAL_DESTINO,
                    snapshot: ['traslado_tienda' => $json['segundo_traslado'], 'archivos' => $pedido->documentos()->whereIn('id', $documentos)->get()->map(fn ($doc) => [
                        'ruta' => $doc->ruta_archivo, 'nombre' => $doc->nombre_original, 'mime_type' => $doc->mime_type,
                    ])->all()]
                );
            }

            app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_traslado_solicitado', 'Traslado solicitado. Pendiente de autorización de gerencia.', [self::PERMISO], $usuario->id, true);

            return $pedido->fresh(['sucursalDestino', 'estatus']);
        });
    }
}
