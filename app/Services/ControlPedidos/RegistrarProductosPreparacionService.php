<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RegistrarProductosPreparacionService
{
    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, array $lineas): array
    {
        if ((! $usuario->can('control_pedidos.tienda.responder') && ! $usuario->can('control_pedidos.tienda.apartado.separar'))
            || (int) $tarea->asignada_a_id !== (int) $usuario->id || $tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
            throw ValidationException::withMessages(['tarea' => 'Tome la tarea antes de registrar su preparación.']);
        }
        $anteriores = $tarea->productos()->get();
        $ids = collect($lineas)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        if ($anteriores->filter(fn ($p) => trim((string) $p->sku) !== '' && ! $ids->contains($p->id))->isNotEmpty()) {
            throw ValidationException::withMessages(['lineas' => 'Conserve las piezas registradas; use cantidad cero y explique el retiro.']);
        }
        $rutas = [];
        try {
            foreach ($lineas as $orden => $linea) {
                $fila = ! empty($linea['id']) ? $anteriores->firstWhere('id', (int) $linea['id']) : null;
                if (! empty($linea['id']) && ! $fila) {
                    throw ValidationException::withMessages(['lineas' => 'La pieza no pertenece a esta tarea.']);
                }
                $producto = ! empty($linea['producto_id']) ? Producto::where('activo', true)->find($linea['producto_id']) : null;
                if (! empty($linea['producto_id']) && (! $producto || ! $producto->inventarios()->where('almacen_id', $tarea->almacen_id)->exists()
                    && ! ($fila && (int) $fila->producto_id === (int) $producto->id && app(\App\Services\Traspasos\AbastecerPreparacionService::class)->productoRecibido($fila->id)))) {
                    throw ValidationException::withMessages(['lineas' => 'Seleccione un producto del catálogo del almacén de preparación.']);
                }
                $sku = trim((string) ($producto?->sku ?? $fila?->sku ?? $linea['sku'] ?? ''));
                $descripcion = trim((string) ($producto?->descripcion ?? $fila?->descripcion_snapshot ?? $linea['descripcion'] ?? ''));
                $cantidad = (int) ($linea['cantidad'] ?? 0);
                $estado = $linea['estado_fisico'] ?? 'bueno';
                $observacion = trim((string) ($linea['observacion'] ?? ''));
                if ($sku === '' || $descripcion === '' || $cantidad < 0 || ! in_array($estado, PedidoBmaRevisionProducto::ESTADOS, true)
                    || (($cantidad === 0 || $estado !== 'bueno') && $observacion === '') || ($estado === 'sin_existencia' && $cantidad > 0)) {
                    throw ValidationException::withMessages(['lineas.'.$orden => 'Seleccione producto y cantidad; describa el faltante, retiro o detalle.']);
                }
                $valores = ['pedido_bma_origen_id' => $tarea->pedido_bma_id, 'producto_id' => $producto?->id ?? $fila?->producto_id,
                    'sku' => $sku, 'descripcion_snapshot' => $descripcion, 'cantidad_solicitada' => $fila?->cantidad_solicitada ?? $cantidad,
                    'cantidad_encontrada' => $cantidad, 'estado_fisico' => $estado, 'observacion' => $observacion ?: null, 'orden' => $orden];
                if ($fila) {
                    if (((int) $valores['producto_id'] !== (int) $fila->producto_id || (int) $valores['cantidad_encontrada'] !== (int) $fila->cantidad_encontrada)
                        && \App\Models\SolicitudTraspasoProducto::where('tarea_producto_receptor_id', $fila->id)->exists()) {
                        throw ValidationException::withMessages(['lineas' => 'La pieza está vinculada a abastecimiento. Sus cantidades se actualizan por llegada; tramite una corrección autorizada para sustituir o ajustar.']);
                    }
                    $fila->update($valores);
                } else {
                    $fila = $tarea->productos()->create($valores);
                }
                foreach ($linea['evidencias'] ?? [] as $archivo) {
                    if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                        continue;
                    }
                    $ruta = $archivo->store('pedidos_bma/tareas_preparacion/'.$tarea->id, 'public');
                    $rutas[] = $ruta;
                    $tarea->documentos()->create(['pedido_bma_tarea_producto_id' => $fila->id, 'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_PRODUCTO,
                        'ruta_interna' => $ruta, 'nombre_original' => $archivo->getClientOriginalName(), 'mime_type' => $archivo->getMimeType(),
                        'tamano_bytes' => $archivo->getSize(), 'hash_sha256' => hash_file('sha256', $archivo->getRealPath()), 'subido_por_id' => $usuario->id, 'subido_at' => now()]);
                }
            }
            $tarea->productos()->where(fn ($q) => $q->whereNull('sku')->orWhere('sku', ''))->delete();

            return $rutas;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutas);
            throw $e;
        }
    }

    public function guardarGenerales(PedidoBmaTareaPreparacion $tarea, User $usuario, array $archivos): array
    {
        $rutas = [];
        try {
            foreach ($archivos as $archivo) {
                if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                    continue;
                }
                $ruta = $archivo->store('pedidos_bma/tareas_preparacion/'.$tarea->id, 'public');
                $rutas[] = $ruta;
                $tarea->documentos()->create(['tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL, 'ruta_interna' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(), 'mime_type' => $archivo->getMimeType(), 'tamano_bytes' => $archivo->getSize(),
                    'hash_sha256' => hash_file('sha256', $archivo->getRealPath()), 'subido_por_id' => $usuario->id, 'subido_at' => now()]);
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutas);
            throw $e;
        }

        return $rutas;
    }
}
