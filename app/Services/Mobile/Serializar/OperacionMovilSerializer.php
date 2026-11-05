<?php

namespace App\Services\Mobile\Serializar;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\SolicitudFactura;
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
            'costo' => $this->decimal($producto?->costos?->costo, 2),
            'precio_venta' => $this->decimal($producto?->precio_publico, 2),
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
            'motivo_operacion' => $solicitud->motivo_operacion,
            'tipo_cliente' => $solicitud->relationLoaded('tipoCliente') && $solicitud->tipoCliente ? [
                'id' => $solicitud->tipoCliente->id,
                'nombre' => $solicitud->tipoCliente->nombre,
            ] : null,
            'lista_descuento' => $solicitud->relationLoaded('listaDescuento') && $solicitud->listaDescuento ? [
                'id' => $solicitud->listaDescuento->id,
                'nombre' => $solicitud->listaDescuento->nombre,
            ] : null,
            'lista_rebaja' => $solicitud->relationLoaded('listaRebaja') && $solicitud->listaRebaja ? [
                'id' => $solicitud->listaRebaja->id,
                'nombre' => $solicitud->listaRebaja->nombre,
            ] : null,
            'banco' => $solicitud->relationLoaded('banco') && $solicitud->banco ? [
                'id' => $solicitud->banco->id,
                'nombre' => $solicitud->banco->nombre,
            ] : null,
            'cotizacion' => [
                'solicitar_cotizacion' => (bool) $solicitud->solicitar_cotizacion,
                'monto_cotizado' => $solicitud->monto_cotizado,
                'monto_aplicado_al_cliente' => $solicitud->monto_aplicado_al_cliente,
                'monto_final_tentativo' => $solicitud->monto_final_tentativo,
                'total_proyectado_neto' => $solicitud->total_proyectado_neto,
            ],
            'factura' => $this->facturaEnSolicitud($solicitud),
        ];
    }

    /**
     * Solicitud de facturación. Sin XML, PDF, comprobantes, enlaces ni el paquete fiscal completo.
     *
     * @return array<string, mixed>
     */
    public function solicitudFactura(SolicitudFactura $factura): array
    {
        return [
            'id' => $factura->id,
            'folio' => $factura->folio,
            'legacy_solicitud_id' => $factura->legacy_solicitud_id,
            'destinatario_tipo' => $factura->destinatario_tipo,
            'razon_social' => $factura->razon_social,
            'observaciones_vendedor' => $factura->observaciones_vendedor,
            'motivo_respuesta' => $factura->motivo_respuesta,
            'motivo_incorrecta' => $factura->motivo_incorrecta,
            'campos_incorrectos' => $factura->campos_incorrectos ?? [],
            'created_at' => $factura->created_at?->toIso8601String(),
            'respondida_at' => $factura->respondida_at?->toIso8601String(),
            'formulario_enviado_at' => $factura->formulario_enviado_at?->toIso8601String(),
            'formulario_respondido_at' => $factura->formulario_respondido_at?->toIso8601String(),
            'tiene_voucher' => (bool) $factura->tiene_voucher,
            'tiene_pdf_emitido' => (bool) $factura->tiene_pdf_emitido,
            'tiene_xml' => (bool) $factura->tiene_xml,
            'tiene_archivo_fiscal' => (bool) $factura->tiene_archivo_fiscal,
            'identificador_fiscal' => $this->identificadorFiscal($factura->datos_fiscales),
            'cliente' => $factura->cliente ? [
                'id' => $factura->cliente->id,
                'numero_cliente' => $factura->cliente->numero_cliente,
                'nombre' => $factura->cliente->nombre,
            ] : null,
            'vendedor' => $factura->vendedor ? [
                'id' => $factura->vendedor->id,
                'name' => $factura->vendedor->name,
            ] : null,
            'respondida_por' => $factura->relationLoaded('respondidaPor') && $factura->respondidaPor ? [
                'id' => $factura->respondidaPor->id,
                'name' => $factura->respondidaPor->name,
            ] : null,
            'departamento' => $factura->departamento ? [
                'id' => $factura->departamento->id,
                'nombre' => $factura->departamento->nombre,
            ] : null,
            'estado' => $factura->estado ? [
                'id' => $factura->estado->id,
                'nombre' => $factura->estado->nombre,
            ] : null,
            'banco' => $factura->relationLoaded('banco') && $factura->banco ? [
                'id' => $factura->banco->id,
                'nombre' => $factura->banco->nombre,
            ] : null,
            'receptor' => $factura->relationLoaded('receptorFiscal') && $factura->receptorFiscal ? [
                'id' => $factura->receptorFiscal->id,
                'codigo_interno' => $factura->receptorFiscal->codigo_interno,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facturaEnSolicitud(SolicitudTag $solicitud): array
    {
        $datos = [
            'razon_social' => $solicitud->factura_razon_social,
            'tiene_datos_fiscales' => is_array($solicitud->factura_datos_fiscales) && $solicitud->factura_datos_fiscales !== [],
            'tiene_archivo' => filled($solicitud->archivo_facturas_path),
            'identificador_fiscal' => $this->identificadorFiscal($solicitud->factura_datos_fiscales),
        ];

        if ($solicitud->relationLoaded('remisionesFactura')) {
            $datos['remisiones'] = $solicitud->remisionesFactura->map(fn ($remision) => [
                'id' => $remision->id,
                'nombre_original' => $remision->nombre_original,
                'orden' => $remision->orden,
            ])->values()->all();
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>|null  $datos
     * @return array{rfc: ?string, uso_factura: ?string, nombre_razon_social: ?string}
     */
    private function identificadorFiscal(?array $datos): array
    {
        return [
            'rfc' => $this->texto($datos['rfc'] ?? null),
            'uso_factura' => $this->texto($datos['uso_factura'] ?? null),
            'nombre_razon_social' => $this->texto($datos['nombre_razon_social'] ?? null),
        ];
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
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
