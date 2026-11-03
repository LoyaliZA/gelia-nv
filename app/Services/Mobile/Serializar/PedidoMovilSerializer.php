<?php

namespace App\Services\Mobile\Serializar;

use App\Models\ControlPedidos\PedidoBma;

class PedidoMovilSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(PedidoBma $pedido): array
    {
        $tarea = $pedido->getAttribute('tarea_preparacion');

        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'folio_remision' => $pedido->folio_remision,
            'numero_remision' => $pedido->numero_remision,
            'fecha' => $pedido->fecha?->toDateString(),
            'prioridad_md' => (bool) $pedido->prioridad_md,
            'es_resguardo' => (bool) $pedido->es_resguardo,
            'estatus_envio' => $pedido->estatus_envio,
            'estatus_sucursal' => $pedido->estatus_sucursal,
            'numero_rastreo' => $pedido->numero_rastreo,
            'total_mercancia' => $pedido->total_mercancia,
            'costo_envio' => $pedido->costo_envio,
            'total_a_cobrar' => $pedido->total_a_cobrar,
            'cantidad_piezas' => $pedido->cantidad_piezas,
            'cliente' => $this->cliente($pedido),
            'vendedor' => $this->usuario($pedido->vendedor),
            'estatus' => $this->estatus($pedido),
            'origen' => $this->catalogo($pedido->origen),
            'almacen' => $this->almacen($pedido->almacen),
            'sucursal_destino' => $pedido->sucursalDestino ? [
                'id' => $pedido->sucursalDestino->id,
                'nombre' => $pedido->sucursalDestino->nombre,
            ] : null,
            'paqueteria' => $this->catalogo($pedido->paqueteria),
            'puede_editar' => (bool) $pedido->getAttribute('puede_editar'),
            'puede_cancelar' => (bool) $pedido->getAttribute('puede_cancelar'),
            'esta_esperando_pago' => (bool) $pedido->getAttribute('esta_esperando_pago'),
            'progreso' => $pedido->getAttribute('progreso'),
            'tarea_preparacion' => is_array($tarea) ? [
                'id' => $tarea['id'] ?? null,
                'estado' => $tarea['estado'] ?? null,
                'estado_label' => $tarea['estado_label'] ?? null,
                'fecha_limite' => $tarea['fecha_limite'] ?? null,
                'modalidad' => $tarea['modalidad'] ?? null,
                'almacen' => $tarea['almacen'] ?? null,
                'pago_confirmado' => $tarea['pago_confirmado'] ?? null,
                'salida_autorizada' => $tarea['salida_autorizada'] ?? null,
            ] : null,
            'referencia_captura' => $pedido->getAttribute('referencia_captura'),
            'updated_at' => $pedido->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detalle(PedidoBma $pedido): array
    {
        return array_merge($this->resumen($pedido), [
            'contacto_nombre' => $pedido->contacto_nombre_snapshot,
            'contacto_telefono' => $pedido->contacto_telefono_snapshot,
            'codigo_postal' => $pedido->codigo_postal,
            'domicilio_entrega' => $pedido->domicilio_entrega,
            'comentarios' => $pedido->comentarios_drive,
            'numero_cajas' => $pedido->numero_cajas,
            'peso_real_kg' => $pedido->peso_real_kg,
            'motivo_cancelacion' => $pedido->motivo_cancelacion,
            'zona' => $this->catalogo($pedido->zona),
            'tipo_guia' => $this->catalogo($pedido->tipoGuia),
            'tarea_preparacion' => $pedido->getAttribute('tarea_preparacion'),
            'cancelacion_operativa' => $pedido->getAttribute('cancelacion_operativa'),
            'historial' => $pedido->relationLoaded('historial')
                ? $pedido->historial->sortByDesc('id')->take(20)->map(fn ($evento) => [
                    'id' => $evento->id,
                    'created_at' => $evento->created_at?->toIso8601String(),
                    'usuario' => $this->usuario($evento->usuario),
                    'estatus_anterior' => $evento->estatusAnterior?->nombre_visual,
                    'estatus_nuevo' => $evento->estatusNuevo?->nombre_visual,
                ])->values()->all()
                : [],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cliente(PedidoBma $pedido): ?array
    {
        $cliente = $pedido->cliente;
        if ($cliente === null) {
            return null;
        }

        return [
            'id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function usuario(mixed $usuario): ?array
    {
        if ($usuario === null) {
            return null;
        }

        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function estatus(PedidoBma $pedido): ?array
    {
        $estatus = $pedido->estatus;
        if ($estatus === null) {
            return null;
        }

        return [
            'id' => $estatus->id,
            'nombre' => $estatus->nombre_visual,
            'fase_ciclo' => $estatus->fase_ciclo,
            'etiqueta' => $estatus->etiquetaSemantica((bool) $pedido->es_resguardo, $pedido->origen?->requiere_logistica),
            'color_hex' => $estatus->color_hex,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function catalogo(mixed $catalogo): ?array
    {
        if ($catalogo === null) {
            return null;
        }

        return [
            'id' => $catalogo->id,
            'codigo' => $catalogo->codigo ?? null,
            'nombre' => $catalogo->nombre ?? $catalogo->nombre_visual ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function almacen(mixed $almacen): ?array
    {
        if ($almacen === null) {
            return null;
        }

        return [
            'id' => $almacen->id,
            'codigo' => $almacen->codigo,
            'nombre' => $almacen->nombre,
        ];
    }
}
