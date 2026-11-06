<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Producto;
use App\Models\User;
use App\Support\ControlPedidos\InstruccionEntrega;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Responde al anexo solicitado. No compromete inventario ni declara separación física. */
final class AtenderAnexoTiendaService
{
    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, array $datos, ?UploadedFile $foto): PedidoBma
    {
        abort_unless($usuario->can('control_pedidos.tienda.responder') || $usuario->can('control_pedidos.tienda.apartado.separar'), 403);
        $ruta = null;
        try {
            return DB::transaction(function () use ($tarea, $usuario, $datos, $foto, &$ruta) {
                $tarea = InstruccionEntrega::bloquearParaEjecutar($tarea);
                if ((int) $tarea->asignada_a_id !== (int) $usuario->id || ! in_array($tarea->estado, ['EN_ATENCION', 'RESPONDIDA'], true)) {
                    throw ValidationException::withMessages(['tarea' => 'Solo el auxiliar asignado atiende el anexo pendiente.']);
                }
                $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($tarea->pedido_bma_id);
                if (($datos['decision'] ?? '') === 'separar') {
                    return $this->separar($pedido, $tarea, $usuario, (int) $datos['indice']);
                }
                if ($pedido->pedidoPagadoORemisionado()) {
                    throw ValidationException::withMessages(['anexo' => 'El pedido ya está pagado o remisionado. Use su gestión documentada.']);
                }
                $json = $pedido->solicitud_preparacion_json ?? [];
                $indice = (int) $datos['indice'];
                $anexo = $json['anexo_sin_remision'][$indice] ?? null;
                if (! is_array($anexo) || isset($anexo['respondida_at'])) {
                    throw ValidationException::withMessages(['anexo' => 'Este anexo no está pendiente de respuesta. Actualice el pedido.']);
                }
                $disponible = $datos['decision'] === 'disponible';
                $nota = trim((string) ($datos['nota'] ?? ''));
                $piezas = [];
                if ($disponible) {
                    if (! $foto || empty($datos['piezas'])) {
                        throw ValidationException::withMessages(['piezas' => 'Identifique las piezas disponibles y adjunte una foto del anexo.']);
                    }
                    foreach ($datos['piezas'] as $fila) {
                        $producto = Producto::query()->where('activo', true)->find($fila['producto_id']);
                        if (! $producto || ! $tarea->almacen_id || ! DB::table('inventarios')->where('almacen_id', $tarea->almacen_id)->where('producto_id', $producto->id)->exists()) {
                            throw ValidationException::withMessages(['piezas' => 'Seleccione productos del almacén de esta tarea.']);
                        }
                        if (($anexo['entrada'] ?? '') === 'catalogo' && strcasecmp((string) $anexo['sku'], (string) $producto->sku) !== 0) {
                            throw ValidationException::withMessages(['piezas' => 'Responda sobre la pieza solicitada. No sustituya el anexo por otra pieza en esta respuesta.']);
                        }
                        $piezas[] = ['producto_id' => $producto->id, 'sku' => $producto->sku, 'descripcion' => $producto->descripcion, 'cantidad' => (int) $fila['cantidad']];
                    }
                    if (($anexo['entrada'] ?? '') === 'catalogo' && array_sum(array_column($piezas, 'cantidad')) !== (int) $anexo['cantidad']) {
                        throw ValidationException::withMessages(['piezas' => 'Si hay cantidades faltantes, repórtelas a Ventas para acordarlas con el cliente.']);
                    }
                    $ruta = $foto->store('pedidos_bma/'.$pedido->id.'/anexo', 'public');
                    $documento = $pedido->documentos()->create([
                        'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        'ruta_archivo' => $ruta, 'nombre_original' => $foto->getClientOriginalName(),
                        'mime_type' => $foto->getMimeType(), 'tamano_bytes' => $foto->getSize(),
                        'activo' => true, 'comentario' => 'Disponibilidad del anexo; no confirma separación.',
                    ]);
                    $anexo['evidencia_respuesta_id'] = $documento->id;
                } elseif ($nota === '') {
                    throw ValidationException::withMessages(['nota' => 'Indique el faltante o detalle para que Ventas lo consulte con el cliente.']);
                }
                $anexo = array_merge($anexo, [
                    'pendiente_identificacion' => false, 'pendiente_revision' => false,
                    'estado' => $disponible ? 'disponible' : 'con_detalle',
                    'piezas_respuesta' => $piezas, 'resultado' => $nota ?: 'Disponibilidad confirmada. Falta el cierre de la consulta y la separación física del anexo.',
                    'respondida_at' => now()->toIso8601String(), 'respondida_por_id' => $usuario->id, 'respondida_por' => $usuario->name,
                ]);
                if (! empty($anexo['sustituye_revision_id'])) {
                    $lineasRevision = $disponible ? $piezas : [['descripcion' => $anexo['descripcion'], 'sku' => $anexo['sku'] ?? null, 'cantidad' => $anexo['cantidad'] ?: 1]];
                    foreach ($lineasRevision as $linea) {
                        $pedido->revisionesProducto()->create([
                            'orden' => (int) $pedido->revisionesProducto()->max('orden') + 1,
                            'descripcion_producto' => $linea['descripcion'], 'sku' => $linea['sku'] ?? null,
                            'producto_id' => $linea['producto_id'] ?? null, 'cantidad_solicitada' => $linea['cantidad'],
                            'cantidad_encontrada' => $disponible ? $linea['cantidad'] : 0,
                            'estado_fisico' => $disponible ? 'bueno' : 'sin_existencia', 'comentario' => $nota,
                        ]);
                    }
                }
                $json['anexo_sin_remision'][$indice] = $anexo;
                $pedido->update(['solicitud_preparacion_json' => $json]);
                $pedido->refresh();
                if (! $pedido->tieneAjusteTiendaPendiente()) {
                    $json['revision_disponibilidad']['aceptacion_vigente'] = true;
                    $pedido->update(['solicitud_preparacion_json' => $json]);
                }
                app(NotificarPedidoBmaService::class)->ejecutar($pedido->fresh(['cliente', 'vendedor']), 'pedido_disponibilidad_respondida',
                    $disponible ? 'Tienda respondió la disponibilidad del anexo. Revise la respuesta con el cliente.' : 'Tienda reportó un faltante o detalle en el anexo: '.$nota,
                    ['control_pedidos.crear'], $usuario->id, true);

                return $pedido->fresh();
            });
        } catch (\Throwable $e) {
            if ($ruta) {
                Storage::disk('public')->delete($ruta);
            }
            throw $e;
        }
    }

    private function separar(PedidoBma $pedido, PedidoBmaTareaPreparacion $tarea, User $usuario, int $indice): PedidoBma
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        $anexo = $json['anexo_sin_remision'][$indice] ?? null;
        if (($anexo['estado'] ?? null) === 'revisado_conjunto') {
            if (isset($anexo['separada_at'])) {
                return $pedido;
            }
            if (! $pedido->consultaCerrada() || $pedido->tieneAjusteTiendaPendiente()) {
                throw ValidationException::withMessages(['anexo' => 'Espere el cierre vigente antes de separar el anexo.']);
            }
            $json['anexo_sin_remision'][$indice]['separada_at'] = now()->toIso8601String();
            $json['anexo_sin_remision'][$indice]['separada_por_id'] = $usuario->id;
            $pedido->update(['solicitud_preparacion_json' => $json]);
            app(RegistrarHistorialPedidoService::class)->ejecutar($pedido->id, $usuario->id,
                $pedido->catalogo_estatus_pedido_id, $pedido->catalogo_estatus_pedido_id,
                'Auxiliar confirmó la separación del anexo conforme a la captura actualizada.', 'separacion_anexo_tienda');

            return $pedido->fresh();
        }
        if (! is_array($anexo) || ($anexo['estado'] ?? null) !== 'disponible' || empty($anexo['piezas_respuesta'])) {
            throw ValidationException::withMessages(['anexo' => 'Responda la disponibilidad del anexo antes de confirmar su separación.']);
        }
        if (isset($anexo['separada_at'])) {
            return $pedido;
        }
        if (! $pedido->consultaCerrada() || $pedido->tieneAjusteTiendaPendiente()) {
            throw ValidationException::withMessages(['anexo' => 'Espere el cierre vigente de la consulta antes de separar el anexo.']);
        }
        $productos = [];
        foreach ($anexo['piezas_respuesta'] as $orden => $pieza) {
            app(CompromisoInventarioTienda::class)->comprometer($pedido, (int) $tarea->almacen_id, (int) $pieza['producto_id'], (string) $pieza['cantidad'], 'anexo:'.$indice.':'.$orden, (string) $pieza['sku']);
            $productos[] = $tarea->productos()->create([
                'pedido_bma_origen_id' => $pedido->id, 'producto_id' => $pieza['producto_id'],
                'sku' => $pieza['sku'], 'descripcion_snapshot' => $pieza['descripcion'],
                'cantidad_solicitada' => $pieza['cantidad'], 'cantidad_encontrada' => $pieza['cantidad'],
                'estado_fisico' => 'bueno', 'orden' => (int) $tarea->productos()->max('orden') + 1,
            ])->id;
        }
        $pedido->refresh();
        $json = $pedido->solicitud_preparacion_json;
        $json['anexo_sin_remision'][$indice]['separada_at'] = now()->toIso8601String();
        $json['anexo_sin_remision'][$indice]['separada_por_id'] = $usuario->id;
        $json['anexo_sin_remision'][$indice]['productos_tarea_ids'] = $productos;
        $pedido->update(['solicitud_preparacion_json' => $json]);
        if ($cumplimiento = $tarea->cumplimientoFisico) {
            $cumplimiento->update(['cantidad' => (int) $tarea->productos()->sum('cantidad_encontrada'), 'version' => $cumplimiento->version + 1]);
        }
        app(NotificarPedidoBmaService::class)->ejecutar($pedido->fresh(['cliente', 'vendedor']), 'pedido_disponibilidad_respondida', 'Tienda confirmó la separación física del anexo.', ['control_pedidos.crear'], $usuario->id, true);

        return $pedido->fresh();
    }
}
