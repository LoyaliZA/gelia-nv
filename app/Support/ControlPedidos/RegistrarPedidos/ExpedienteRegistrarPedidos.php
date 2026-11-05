<?php

namespace App\Support\ControlPedidos\RegistrarPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ResponderDisponibilidadInicialService;

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
            'instruccionEntregaVigente.modalidad',
            'revisionesProducto',
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
            'atencion_confirmada' => (bool) (\App\Support\ControlPedidos\AtencionMismoDiaTienda::datos($pedido)['confirmada'] ?? false),
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
            'revision_disponibilidad' => is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : null,
            'decision_pendiente' => $this->decisionPendiente($json, $area),
            'excepciones' => $this->excepcionesPendientes($pedido, $json),
            'tienda_md' => \App\Support\ControlPedidos\AtencionMismoDiaTienda::datos($pedido),
            'cobertura_elegida' => is_array($json['cobertura_tienda'] ?? null) ? $json['cobertura_tienda'] : null,
            'decisiones' => $this->decisiones($pedido),
            'segundo_traslado' => is_array($json['segundo_traslado'] ?? null) ? $json['segundo_traslado'] : null,
            'credito' => is_array($json['credito_wizerp'] ?? null) ? $json['credito_wizerp'] : null,
            'anexo' => [
                'sin_remision' => is_array($json['anexo_sin_remision'] ?? null) ? $json['anexo_sin_remision'] : [],
                'relacionados' => is_array($json['anexos_relacionados'] ?? null) ? $json['anexos_relacionados'] : [],
                'integrado' => (bool) ($json['anexo_integrado'] ?? false),
            ],
            'retiro_recoleccion' => is_array($json['retiro_recoleccion'] ?? null) ? $json['retiro_recoleccion'] : null,
            'liberacion_20' => is_array($json['liberacion_fisica_20'] ?? null) ? $json['liberacion_fisica_20'] : null,
            'cambio_modalidad' => is_array($json['cambio_modalidad'] ?? null) ? $json['cambio_modalidad'] : null,
            'siguiente' => $this->siguiente($json, $area, $instruccion),
            'respuesta' => $instruccion ? [
                'estado' => $instruccion->estado,
                'vigente' => true,
                'modalidad' => $instruccion->modalidad?->codigo,
                'ubicacion' => $ubicacion ?: null,
                'cumplimiento' => $instruccion->cumplimientoFisico?->estado,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function decisionPendiente(array $json, string $area): ?string
    {
        $estado = $json['revision_disponibilidad']['estado'] ?? null;
        if ($estado === 'excepciones_pendientes') {
            return ResponderDisponibilidadInicialService::DECISION_EXCEPCIONES;
        }
        if ($estado === 'sin_excepciones') {
            return $area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA
                ? ResponderDisponibilidadInicialService::DECISION_TIENDA
                : ResponderDisponibilidadInicialService::DECISION_CEDIS;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array{descripcion: string, cantidad: ?int, detalle: ?string, estado_fisico: string}>
     */
    private function excepcionesPendientes(PedidoBma $pedido, array $json): array
    {
        if (($json['revision_disponibilidad']['estado'] ?? null) !== 'excepciones_pendientes') {
            return [];
        }

        return $pedido->revisionesProducto()
            ->whereNull('resolucion')
            ->whereIn('estado_fisico', ['regular', 'malo', 'danado', 'sin_existencia'])
            ->orderBy('orden')
            ->get(['id', 'descripcion_producto', 'cantidad_solicitada', 'estado_fisico', 'comentario'])
            ->map(fn ($revision) => [
                'id' => $revision->id,
                'descripcion' => (string) $revision->descripcion_producto,
                'cantidad' => $revision->cantidad_solicitada,
                'detalle' => $revision->comentario,
                'estado_fisico' => (string) $revision->estado_fisico,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    private function decisiones(PedidoBma $pedido): array
    {
        return $pedido->revisionesProducto
            ->filter(fn ($revision) => filled($revision->resolucion))
            ->map(fn ($revision) => [
                'id' => $revision->id,
                'descripcion' => (string) $revision->descripcion_producto,
                'resolucion' => (string) $revision->resolucion,
                'etiqueta' => $revision->resolucion_etiqueta,
                'nota' => $revision->resolucion_nota,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function siguiente(array $json, string $area, mixed $instruccion): string
    {
        $estado = $json['revision_disponibilidad']['estado'] ?? null;
        if (! in_array($estado, ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'], true)) {
            return $area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA
                ? 'Enviado a Preparación Tienda. Se espera la revisión del archivo.'
                : 'Enviado a Control Pedidos. Se espera la consulta de disponibilidad.';
        }
        if (! is_array($json['cobertura_tienda'] ?? null)) {
            return 'Falta elegir la cobertura: pago al recoger, transferencia o crédito.';
        }
        $reporte = $json['tienda_md_reporte']['estado'] ?? null;
        if ($reporte === 'tardio' && ($json['atencion_tienda'] ?? '') === 'prevista') {
            return 'El reporte de Mismo día llegó fuera de corte. Queda pendiente de acuerdo.';
        }
        $credito = $json['credito_wizerp']['resultado'] ?? null;
        if (($json['cobertura_tienda']['tipo'] ?? null) === 'credito' && $credito === 'bloqueado') {
            return 'El crédito está bloqueado. Falta regularizarlo. No se aprueba por el paso del tiempo.';
        }
        $fisico = $instruccion?->cumplimientoFisico?->estado;
        if ($fisico === 'ENTREGADA') {
            return 'Entrega registrada.';
        }
        if ($fisico === 'APARTADO_CONFIRMADO' && ($json['cobertura_tienda']['tipo'] ?? null) === 'apartado') {
            return 'Apartado confirmado. Falta el pago presencial y la fotografía de entrega.';
        }

        return 'Cobertura registrada. Siguiente: comprobante o crédito, remisión y preparación. Aún no hay entrega.';
    }
}
