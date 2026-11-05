<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Producto;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Decisiones sobre excepciones de la consulta. Nunca confirma pago ni libera stock. */
final class AccionesDisponibilidadTiendaService
{
    public function ejecutar(PedidoBma $pedido, User $actor, array $datos): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $actor, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            abort_unless($actor->can('control_pedidos.crear') && (int) $pedido->vendedor_id === (int) $actor->id, 403);
            $json = $pedido->solicitud_preparacion_json ?? [];
            if (($json['modalidad_entrega'] ?? '') !== 'tienda' || $pedido->cancelado_at || $pedido->empacado_at || $pedido->consultaCerrada() || \App\Support\ControlPedidos\InstruccionEntrega::mercanciaYaSalio($pedido)) {
                throw ValidationException::withMessages(['consulta' => 'Abra la consulta antes de ajustar la disponibilidad. No se modifican pedidos preparados o cancelados.']);
            }
            if (! in_array($json['revision_disponibilidad']['estado'] ?? '', ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'], true)) {
                throw ValidationException::withMessages(['consulta' => 'Falta una respuesta de disponibilidad vigente.']);
            }
            $revision = $pedido->revisionesProducto()->lockForUpdate()->findOrFail($datos['revision_id']);
            $accion = $datos['accion'];
            $anterior = $json['acciones_disponibilidad'][(string) $revision->id] ?? null;
            if ($anterior) {
                if (($anterior['solicitud_id'] ?? '') === $datos['solicitud_id']) {
                    return $pedido;
                }
                throw ValidationException::withMessages(['pieza' => 'Esta pieza ya tiene una decisión registrada. Actualice la consulta.']);
            }
            if ($revision->resolucion) {
                throw ValidationException::withMessages(['pieza' => 'La pieza ya fue atendida. Actualice la consulta.']);
            }
            if ($accion === 'confirmar' && $revision->estado_fisico === 'sin_existencia') {
                throw ValidationException::withMessages(['pieza' => 'Una pieza sin existencias solo puede retirarse o sustituirse.']);
            }
            if ($pedido->pedidoPagadoORemisionado()) {
                throw ValidationException::withMessages(['consulta' => 'El pedido tiene pago verificado o remisión. El ajuste requiere su gestión documentada; se conservan sus documentos e importes.']);
            }
            $nueva = null;
            if ($accion === 'sustituir') {
                if (($datos['entrada'] ?? 'catalogo') === 'catalogo') {
                    $producto = Producto::query()->where('activo', true)->findOrFail($datos['producto_id']);
                    if ($producto->sku === $revision->sku) {
                        throw ValidationException::withMessages(['producto' => 'Seleccione una pieza diferente.']);
                    }
                    $nueva = ['entrada' => 'catalogo', 'producto_id' => $producto->id, 'sku' => $producto->sku,
                        'descripcion' => $producto->descripcion, 'cantidad' => $revision->cantidad_solicitada ?: 1, 'pendiente_revision' => true];
                } else {
                    $archivo = $datos['evidencia'];
                    $documento = $pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_ANEXO_PIEZAS,
                        'ruta_archivo' => $archivo->store('pedidos_bma/'.$pedido->id.'/sustitucion', 'public'),
                        'nombre_original' => $archivo->getClientOriginalName(), 'mime_type' => $archivo->getMimeType(),
                        'tamano_bytes' => $archivo->getSize(), 'orden' => 1, 'activo' => true, 'comentario' => 'Alternativa solicitada por Ventas']);
                    $nueva = ['entrada' => 'documento', 'documento_id' => $documento->id, 'pendiente_identificacion' => true,
                        'descripcion' => 'Alternativa en documento', 'cantidad' => $revision->cantidad_solicitada ?: 1, 'pendiente_revision' => true];
                }
            }
            // Las excepciones CEDIS son una consulta documental: todavía no hay separación física.
            $fisica = collect($json['compromisos_inventario'] ?? [])->contains(fn ($fila) => $fila['activo'] ?? false)
                || in_array($pedido->instruccionEntregaVigente?->cumplimientoFisico?->estado, ['SEPARADA', 'APARTADO_CONFIRMADO', 'LISTA_PARA_SALIDA', 'DESPACHADA', 'ENTREGADA'], true);
            if ($accion !== 'confirmar' && ($json['area_revision'] ?? '') !== 'control_pedidos' && $fisica) {
                app(RegistrarRetiroPiezasTiendaService::class)->ejecutar($pedido, $actor, [[
                    'revision_id' => $revision->id, 'sku' => $revision->sku ?? '', 'descripcion' => $revision->descripcion_producto,
                    'cantidad' => $revision->cantidad_solicitada ?: 1,
                ]], $accion === 'sustituir' ? 'Sustitución solicitada por el cliente' : 'Retiro confirmado con el cliente');
                $pedido->refresh();
                $json = $pedido->solicitud_preparacion_json;
            }
            $revision->update(['resolucion' => $accion === 'confirmar' ? 'aceptar_condicion' : ($accion === 'retirar' ? 'retirar' : 'sustituir'),
                'resolucion_nota' => $accion === 'confirmar' ? 'El cliente confirma la pieza en el estado reportado.' : 'Decisión de Ventas en consulta.',
                'resolucion_por_id' => $actor->id, 'resolucion_at' => now()]);
            $evento = ['accion' => $accion, 'revision_id' => $revision->id, 'descripcion' => $revision->descripcion_producto,
                'sku' => $revision->sku, 'cantidad' => $revision->cantidad_solicitada, 'estado_reportado' => $revision->estado_fisico,
                'por_id' => $actor->id, 'por' => $actor->name, 'fecha' => now()->toIso8601String(), 'solicitud_id' => $datos['solicitud_id']];
            if ($nueva) {
                $nueva['sustituye_revision_id'] = $revision->id;
                $nueva['solicitado_por'] = $actor->name;
                $nueva['solicitado_at'] = now()->toIso8601String();
                $json['anexo_sin_remision'][] = $nueva;
                $evento['alternativa'] = $nueva;
                $json['revision_disponibilidad']['aceptacion_vigente'] = false;
            }
            $json['acciones_disponibilidad'][(string) $revision->id] = $evento;
            $pedido->update(['solicitud_preparacion_json' => $json]);
            app(RegistrarHistorialPedidoService::class)->ejecutar($pedido->id, $actor->id, $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id, match ($accion) {
                    'confirmar' => 'Cliente confirma el estado reportado de ', 'retirar' => 'Ventas retira de la consulta ', default => 'Ventas solicita sustituir '
                }.$revision->descripcion_producto,
                'decision_disponibilidad_tienda', snapshot: ['disponibilidad' => $evento]);
            $this->notificar($pedido, $actor, 'Ventas registró cambios en las piezas de la consulta.');

            return $pedido->fresh();
        });
    }

    public function notificar(PedidoBma $pedido, User $actor, string $texto): void
    {
        $cedis = ($pedido->solicitud_preparacion_json['area_revision'] ?? '') === 'control_pedidos';
        $tarea = $pedido->instruccionEntregaVigente;
        app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_disponibilidad_ajustada', $texto,
            [$cedis ? 'control_pedidos.cedis' : 'control_pedidos.tienda.ver'], $actor->id, true,
            ['url' => $cedis ? '/control-pedidos/cedis?q='.$pedido->folio : '/control-pedidos/tienda'.($tarea ? '?tarea='.$tarea->id : '')]);
    }

    /** CEDIS identifica la alternativa documental y responde, sin separar ni cobrar. */
    public function responderAlternativa(PedidoBma $pedido, User $actor, array $datos): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $actor, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            abort_unless($actor->can('control_pedidos.cedis') && VisibilidadPedidoBma::puedeVerEnCedis($actor, $pedido), 403);
            $json = $pedido->solicitud_preparacion_json;
            abort_unless(($json['area_revision'] ?? '') === 'control_pedidos', 403);
            $indice = $datos['indice'];
            $anexo = $json['anexo_sin_remision'][$indice] ?? null;
            if (! $anexo || ! empty($anexo['respondida_at']) || $pedido->cancelado_at || $pedido->empacado_at || $pedido->consultaCerrada()) {
                throw ValidationException::withMessages(['anexo' => 'La alternativa ya fue atendida o no está abierta a revisión.']);
            }
            if ($pedido->pedidoPagadoORemisionado()) {
                throw ValidationException::withMessages(['anexo' => 'El pedido tiene pago verificado o remisión. Use su gestión documentada.']);
            }
            if (! empty($anexo['producto_id']) && ! empty($datos['producto_id']) && (int) $anexo['producto_id'] !== (int) $datos['producto_id']) {
                throw ValidationException::withMessages(['producto' => 'Confirme la alternativa solicitada; no cambie su SKU.']);
            }
            $disponible = $datos['decision'] === 'disponible';
            $producto = $disponible ? Producto::findOrFail($datos['producto_id'] ?? $anexo['producto_id'] ?? 0) : null;
            $rev = $pedido->revisionesProducto()->create(['orden' => (int) $pedido->revisionesProducto()->max('orden') + 1,
                'descripcion_producto' => $producto?->descripcion ?? $anexo['descripcion'], 'sku' => $producto?->sku ?? $anexo['sku'] ?? null,
                'producto_id' => $producto?->id, 'cantidad_solicitada' => $anexo['cantidad'] ?: 1,
                'cantidad_encontrada' => $disponible ? ($anexo['cantidad'] ?: 1) : 0, 'estado_fisico' => $disponible ? 'bueno' : 'sin_existencia',
                'comentario' => $datos['nota'] ?? null]);
            $anexo['respondida_at'] = now()->toIso8601String();
            $anexo['respondida_por'] = $actor->name;
            $anexo['estado'] = $disponible ? 'disponible' : 'rechazado';
            $anexo['resultado'] = $datos['nota'] ?? ($disponible ? 'Alternativa disponible' : 'Sin existencias');
            // En CEDIS la separación sucede con la preparación posterior al cierre y pago.
            $anexo['separada_at'] = null;
            $anexo['revision_respuesta_id'] = $rev->id;
            $json['anexo_sin_remision'][$indice] = $anexo;
            $json['revision_disponibilidad']['aceptacion_vigente'] = ! collect($json['anexo_sin_remision'])->contains(fn ($a) => empty($a['respondida_at']));
            $pedido->update(['solicitud_preparacion_json' => $json]);
            app(RegistrarHistorialPedidoService::class)->ejecutar($pedido->id, $actor->id, $pedido->catalogo_estatus_pedido_id, $pedido->catalogo_estatus_pedido_id,
                'CEDIS respondió la alternativa solicitada: '.$rev->descripcion_producto.'. '.$anexo['resultado'], 'respuesta_alternativa_tienda');
            $this->notificar($pedido, $actor, 'CEDIS respondió la alternativa. Ventas debe confirmar la consulta con el cliente.');

            return $pedido->fresh();
        });
    }
}
