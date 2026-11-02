<?php

namespace App\Support\ControlPedidos\RegistrarPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;

/** Lectura del pedido para Registrar pedidos. No crea filas ni confirma Mismo día. */
final class ExpedienteRegistrarPedidos
{
    /**
     * @return array<string, mixed>
     */
    public function desdePedido(PedidoBma $pedido): array
    {
        $pedido->loadMissing([
            'cliente',
            'sucursalDestino',
            'instruccionEntregaVigente.cumplimientoFisico',
            'instruccionEntregaVigente.almacen',
        ]);

        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $cobertura = PoliticaAccionesRegistrarPedidos::coberturaDeCodigo($json['codigo_modalidad'] ?? null);
        $atencion = $json['atencion_tienda'] ?? null;
        $ubicacion = $pedido->instruccionEntregaVigente?->cumplimientoFisico?->ubicacion;
        $instruccion = $pedido->instruccionEntregaVigente;
        $pagoRegistrado = $pedido->pagosExhibicion()->where('activo_para_cobertura', true)->exists();
        $area = DestinoRevisionRegistrarPedidos::areaGuardada($pedido);
        $almacenRevisionId = isset($json['almacen_revision_id']) ? (int) $json['almacen_revision_id'] : null;
        $almacenRevision = $almacenRevisionId
            ? Almacen::query()->whereKey($almacenRevisionId)->value('nombre')
            : ($instruccion?->almacen?->nombre);
        $enviada = DestinoRevisionRegistrarPedidos::yaEnviada($pedido);

        return [
            'cliente_id' => $pedido->cliente_id,
            'numero_cliente' => $pedido->cliente?->numero_cliente,
            'cliente_nombre' => $pedido->cliente?->nombre,
            'departamento_operacion_id' => $pedido->departamento_operacion_id,
            'folio_pedido' => $pedido->folio_remision,
            'folio_interno' => $pedido->folio,
            'cobertura' => $cobertura !== '' ? $cobertura : null,
            'atencion' => $atencion,
            'atencion_confirmada' => false,
            'pago_registrado' => $pagoRegistrado,
            'sucursal' => $pedido->sucursalDestino?->nombre,
            'sucursal_destino_id' => $pedido->sucursal_destino_id,
            'ubicacion' => $ubicacion ?: null,
            'piezas' => is_array($json['piezas_tienda'] ?? null) ? array_values($json['piezas_tienda']) : [],
            'area_revision' => $area !== '' ? $area : null,
            'almacen_revision_id' => $almacenRevisionId ?: null,
            'almacen_revision' => $almacenRevision ?: null,
            'enviada' => $enviada,
            'espera' => $enviada ? DestinoRevisionRegistrarPedidos::espera($area, $almacenRevision ?: null) : null,
            'respuesta' => $instruccion ? [
                'estado' => $instruccion->estado,
                'vigente' => true,
            ] : null,
        ];
    }
}
