<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;

class SnapshotCotizacionSolicitudService
{
    /** Freeze the proposal independently of later edits and client updates. */
    public function construir(SolicitudTag $solicitud, ?array $antes): array
    {
        $solicitud->load(['proceso', 'vendedor', 'listaDescuento', 'tipoCliente']);
        $cotizado = $antes ?? [];
        $proceso = mb_strtoupper($solicitud->proceso?->nombre ?? '');
        $asignaTag = str_contains($proceso, 'ASIGNAR TAG') || str_contains($proceso, 'ASIGNAR CLIENTE');
        $cotizado['monto_venta'] = isset($antes['monto_venta'])
            ? (float) $antes['monto_venta'] + (float) $solicitud->monto_cotizado : null;
        if ($solicitud->catalogo_lista_descuento_id) {
            $cotizado['lista_id'] = $solicitud->catalogo_lista_descuento_id;
            $cotizado['lista_nombre'] = $solicitud->listaDescuento?->nombre;
        }
        if ($solicitud->catalogo_tipo_cliente_id) {
            $cotizado['tipo_cliente_id'] = $solicitud->catalogo_tipo_cliente_id;
            $cotizado['tipo_cliente_nombre'] = $solicitud->tipoCliente?->nombre;
        }
        if ($asignaTag) {
            $cotizado['tag_vendedor_id'] = $solicitud->vendedor_id;
            $cotizado['tag_vendedor_nombre'] = $solicitud->vendedor?->name;
        }

        return [
            'antes' => $antes,
            'cotizado' => $cotizado,
            'proceso_nombre' => $solicitud->proceso?->nombre,
            'lista_descuento_nombre' => $solicitud->listaDescuento?->nombre,
            'tipo_cliente_id' => $solicitud->catalogo_tipo_cliente_id,
            'tipo_cliente_nombre' => $solicitud->tipoCliente?->nombre,
        ];
    }
}
