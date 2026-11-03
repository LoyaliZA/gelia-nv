<?php

namespace App\Services\Mobile\Serializar;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\SolicitudTag;
use App\Models\SolicitudTraspaso;

class OperacionMovilSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function almacen(Almacen $almacen): array
    {
        return [
            'id' => $almacen->id,
            'codigo' => $almacen->codigo,
            'nombre' => $almacen->nombre,
            'activo' => (bool) $almacen->activo,
            'visible_en_pedidos' => (bool) $almacen->visible_en_pedidos,
            'visible_en_traspasos' => (bool) $almacen->visible_en_traspasos,
            'permite_busqueda_productos' => (bool) $almacen->permite_busqueda_productos,
            'permite_salida_pedidos' => (bool) $almacen->permite_salida_pedidos,
            'sucursal' => $almacen->sucursal ? [
                'id' => $almacen->sucursal->id,
                'codigo' => $almacen->sucursal->codigo ?? null,
                'nombre' => $almacen->sucursal->nombre,
            ] : null,
            'tipo' => $almacen->tipoAlmacen ? [
                'id' => $almacen->tipoAlmacen->id,
                'nombre' => $almacen->tipoAlmacen->nombre,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inventario(Inventario $inventario): array
    {
        $producto = $inventario->producto;

        return [
            'id' => $inventario->id,
            'ubicacion' => $inventario->ubicacion,
            'existencia' => $inventario->existencia,
            'apartado' => $inventario->apartado,
            'disponible' => $inventario->disponible,
            'transito_oc' => $inventario->transito_oc,
            'transito_ot' => $inventario->transito_ot,
            'minimo' => $inventario->minimo,
            'maximo' => $inventario->maximo,
            'costo' => $this->decimal($inventario->getAttribute('costo_valor'), 2),
            'precio_venta' => $this->decimal($inventario->getAttribute('precio_venta_valor'), 2),
            'producto' => $producto ? [
                'id' => $producto->id,
                'folio' => $producto->folio,
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'codigo_barras' => $producto->codigo_barras,
                'activo' => (bool) $producto->activo,
                'marca' => $producto->marca?->nombre,
                'categoria' => $producto->categoria?->nombre,
            ] : null,
            'almacen' => $inventario->almacen ? $this->almacen($inventario->almacen) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activo(Activo $activo, bool $detalle = false): array
    {
        $asignacion = $activo->relationLoaded('asignaciones')
            ? $activo->asignaciones->firstWhere('activa', true)
            : null;

        $datos = [
            'id' => $activo->id,
            'folio' => $activo->folio,
            'nombre' => $activo->nombre,
            'descripcion' => $activo->descripcion,
            'estado' => $activo->estado,
            'fecha_adquisicion' => $activo->fecha_adquisicion?->toDateString(),
            'fecha_vencimiento' => $activo->fecha_vencimiento?->toDateString(),
            'valor' => $activo->valor,
            'atributos' => $activo->atributos,
            'tipo' => $activo->tipo ? [
                'id' => $activo->tipo->id,
                'nombre' => $activo->tipo->nombre,
                'slug' => $activo->tipo->slug,
            ] : null,
            'categoria' => $activo->categoria ? [
                'id' => $activo->categoria->id,
                'nombre' => $activo->categoria->nombre,
            ] : null,
            'departamento' => $activo->departamento ? [
                'id' => $activo->departamento->id,
                'nombre' => $activo->departamento->nombre,
            ] : null,
            'area' => $activo->area ? [
                'id' => $activo->area->id,
                'nombre' => $activo->area->nombre,
            ] : null,
            'responsable' => $activo->responsable ? [
                'id' => $activo->responsable->id,
                'name' => $activo->responsable->name,
            ] : null,
            'asignacion_activa' => $asignacion instanceof ActivoAsignacion
                ? $this->asignacion($asignacion)
                : null,
        ];

        if ($detalle) {
            $datos['consulta_token'] = $activo->consulta_token;
            $datos['mantenimiento_activo'] = $activo->relationLoaded('mantenimientoActivo')
                ? $activo->mantenimientoActivo->map(fn ($mantenimiento) => [
                    'id' => $mantenimiento->id,
                    'estado' => $mantenimiento->estado,
                    'fecha_programada' => $mantenimiento->fecha_programada?->toDateString() ?? $mantenimiento->fecha_programada,
                ])->values()->all()
                : [];
        }

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    public function asignacion(ActivoAsignacion $asignacion): array
    {
        return [
            'id' => $asignacion->id,
            'activa' => (bool) $asignacion->activa,
            'firmado' => (bool) $asignacion->firmado,
            'firma_fecha' => $asignacion->firma_fecha?->toIso8601String(),
            'fecha_inicio' => $asignacion->fecha_inicio?->toDateString(),
            'fecha_fin' => $asignacion->fecha_fin?->toDateString(),
            'notas' => $asignacion->notas,
            'usuario' => $asignacion->relationLoaded('usuario') && $asignacion->usuario ? [
                'id' => $asignacion->usuario->id,
                'name' => $asignacion->usuario->name,
            ] : ['id' => $asignacion->user_id],
            'activo' => $asignacion->relationLoaded('activo') && $asignacion->activo ? [
                'id' => $asignacion->activo->id,
                'folio' => $asignacion->activo->folio,
                'nombre' => $asignacion->activo->nombre,
                'estado' => $asignacion->activo->estado,
            ] : ['id' => $asignacion->activo_id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function solicitud(SolicitudTag $solicitud): array
    {
        return [
            'id' => $solicitud->id,
            'monto_cotizado' => $solicitud->monto_cotizado,
            'monto_aplicado_al_cliente' => $solicitud->monto_aplicado_al_cliente,
            'monto_final_tentativo' => $solicitud->monto_final_tentativo,
            'pago_confirmado' => (bool) $solicitud->pago_confirmado,
            'numero_remision' => $solicitud->numero_remision,
            'numero_pedido' => $solicitud->numero_pedido,
            'fecha_operacion' => $solicitud->fecha_operacion?->toDateString(),
            'observaciones_vendedor' => $solicitud->observaciones_vendedor,
            'cancelacion_solicitada_at' => $solicitud->cancelacion_solicitada_at?->toIso8601String(),
            'motivo_cancelacion' => $solicitud->motivo_cancelacion,
            'motivo_incorrecta' => $solicitud->motivo_incorrecta,
            'created_at' => $solicitud->created_at?->toIso8601String(),
            'cliente' => $solicitud->cliente ? [
                'id' => $solicitud->cliente->id,
                'numero_cliente' => $solicitud->cliente->numero_cliente,
                'nombre' => $solicitud->cliente->nombre,
            ] : null,
            'vendedor' => $solicitud->vendedor ? [
                'id' => $solicitud->vendedor->id,
                'name' => $solicitud->vendedor->name,
            ] : null,
            'proceso' => $solicitud->proceso ? [
                'id' => $solicitud->proceso->id,
                'nombre' => $solicitud->proceso->nombre,
                'categoria_flujo' => $solicitud->proceso->categoria_flujo,
            ] : null,
            'estado' => $solicitud->estado ? [
                'id' => $solicitud->estado->id,
                'nombre' => $solicitud->estado->nombre,
            ] : null,
            'departamento' => $solicitud->departamento ? [
                'id' => $solicitud->departamento->id,
                'nombre' => $solicitud->departamento->nombre,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function traspaso(SolicitudTraspaso $traspaso, bool $detalle = false): array
    {
        $datos = [
            'id' => $traspaso->id,
            'folio' => $traspaso->folio,
            'folio_traspaso' => $traspaso->folio_traspaso,
            'total_piezas' => $traspaso->total_piezas,
            'fecha_entrega_estimada' => $traspaso->fecha_entrega_estimada?->toDateString(),
            'estado_fisico_general_origen' => $traspaso->estado_fisico_general_origen,
            'estado_fisico_general_cedis' => $traspaso->estado_fisico_general_cedis,
            'motivo_respuesta' => $traspaso->motivo_respuesta,
            'motivo_incorrecta' => $traspaso->motivo_incorrecta,
            'respondida_at' => $traspaso->respondida_at?->toIso8601String(),
            'tiene_evidencia_respuesta' => (bool) $traspaso->tiene_evidencia_respuesta,
            'cliente' => $traspaso->cliente ? [
                'id' => $traspaso->cliente->id,
                'numero_cliente' => $traspaso->cliente->numero_cliente,
                'nombre' => $traspaso->cliente->nombre,
            ] : null,
            'vendedor' => $traspaso->vendedor ? [
                'id' => $traspaso->vendedor->id,
                'name' => $traspaso->vendedor->name,
            ] : null,
            'departamento' => $traspaso->departamento ? [
                'id' => $traspaso->departamento->id,
                'nombre' => $traspaso->departamento->nombre,
            ] : null,
            'sucursal_solicitante' => $traspaso->sucursalSolicitante ? [
                'id' => $traspaso->sucursalSolicitante->id,
                'nombre' => $traspaso->sucursalSolicitante->nombre,
            ] : null,
            'almacen_origen' => $traspaso->almacenOrigen ? [
                'id' => $traspaso->almacenOrigen->id,
                'codigo' => $traspaso->almacenOrigen->codigo,
                'nombre' => $traspaso->almacenOrigen->nombre,
            ] : null,
            'estado' => $traspaso->estado ? [
                'id' => $traspaso->estado->id,
                'nombre' => $traspaso->estado->nombre,
            ] : null,
        ];

        if ($detalle && $traspaso->relationLoaded('productos')) {
            $datos['productos'] = $traspaso->productos->map(fn ($producto) => [
                'id' => $producto->id,
                'producto_id' => $producto->producto_id,
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'piezas' => $producto->piezas,
            ])->values()->all();
        }

        return $datos;
    }

    private function decimal(mixed $valor, int $decimales): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return number_format((float) $valor, $decimales, '.', '');
    }
}
