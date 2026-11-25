<?php

namespace App\Support\ComisionesEmpaque;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAnexoEnvio;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Support\ControlPedidos\ConsultaMunicipal;

class HuellaFuenteComisionEmpaque
{
    public static function de(int|string $cantidad, mixed $empacadoAt, mixed $canceladoAt, mixed $tipoId, string|array|null $desglose = null): string
    {
        $datos = [
            'cantidad' => (int) $cantidad,
            'empacado_at' => (string) $empacadoAt,
            'cancelado' => (string) $canceladoAt,
            'tipo' => $tipoId ?: null,
        ];
        if ($desglose !== null) {
            $datos['desglose'] = $desglose;
        }

        return hash('sha256', json_encode($datos));
    }

    public static function empaqueMunicipalEnTienda(PedidoBma $pedido): bool
    {
        return ConsultaMunicipal::es($pedido) && (ConsultaMunicipal::seguimiento($pedido)['area'] ?? null) === 'tienda';
    }

    public static function datosComerciales(PedidoBma $pedido, ?PedidoBmaCaja $caja = null, ?PedidoBmaCumplimientoFisico $cumplimiento = null): array
    {
        $remisiones = $caja?->remisionesActivas->map(fn ($remision) => [
            'folio' => $remision->folio_externo ?: ($remision->pedido?->numero_remision ?: $remision->pedido?->folio_remision),
            'pedido_id' => $remision->pedido_bma_id,
            'evidencia_id' => $remision->evidencia_documento_id,
        ])->sortBy(fn ($remision) => json_encode($remision))->values()->all() ?? [];
        if ($remisiones === [] && ($folio = $pedido->numero_remision ?: $pedido->folio_remision)) {
            $remisiones[] = ['folio' => $folio, 'pedido_id' => $pedido->id, 'evidencia_id' => null];
        }

        $tarea = $cumplimiento?->tarea;
        $modalidad = $pedido->solicitud_preparacion_json['modalidad_entrega'] ?? null;
        $evidencia = self::evidenciaComercial($pedido, $cumplimiento);
        $condiciones = collect($evidencia['caratulas'])->pluck('modalidad')->push($evidencia['validacion_salida']['condicion'] ?? null);
        $porCobrar = $pedido->envio_por_cobrar
            || $tarea?->modalidad_cobro === PedidoBmaCaratula::COBRO_POR_COBRAR
            || $condiciones->contains(PedidoBmaCaratula::COBRO_POR_COBRAR);
        $declaradoPagado = $condiciones->contains(PedidoBmaCaratula::COBRO_PAGADO);
        $conflicto = $porCobrar && $declaradoPagado;
        $motivo = match (true) {
            $conflicto => 'sin_informacion',
            $porCobrar => 'por_cobrar',
            (bool) $pedido->cliente_proporciona_guia && filled($caja?->numero_rastreo ?: $pedido->numero_rastreo) => 'guia_cliente',
            default => 'sin_informacion',
        };
        $anexosAprobados = collect($evidencia['anexos'])->where('estado', PedidoBmaAnexoEnvio::ESTATUS_APROBADO)->values()->all();
        $revision = match (true) {
            $motivo !== 'sin_informacion' => null,
            $conflicto => 'Verifique la modalidad de cobro. Las fuentes del pedido declaran condiciones distintas.',
            (bool) $pedido->cliente_proporciona_guia => 'Verifique el número de rastreo de la guía aportada por el cliente.',
            $anexosAprobados !== [] => 'El anexo documenta su propio concepto e importe. Compruebe a qué empaque corresponde.',
            $declaradoPagado => 'Identifique el comprobante que desglosa el transporte declarado como pagado.',
            default => 'Identifique el cobro de transporte, la guía del cliente o el motivo autorizado.',
        };
        $huella = [
            $remisiones, $motivo, $modalidad, $pedido->cliente_id,
            $tarea?->catalogo_modalidad_preparacion_id,
            $tarea?->catalogo_paqueteria_id ?: $pedido->catalogo_paqueteria_id,
            $caja?->catalogo_tipo_guia_id ?: $pedido->catalogo_tipo_guia_id,
            $pedido->numero_rastreo, $caja?->numero_rastreo,
        ];
        if ($evidencia['caratulas'] || $anexosAprobados || $evidencia['modalidad_tarea'] || $evidencia['validacion_salida']) {
            $huella[] = array_replace($evidencia, ['anexos' => $anexosAprobados]);
        }

        return [
            'remisiones' => $remisiones,
            'folios_remision' => collect($remisiones)->pluck('folio')->filter()->unique()->implode(' · '),
            'motivo_fuente' => $motivo,
            'modalidad_envio' => $modalidad,
            'revision_comercial' => $revision,
            'evidencia_comercial' => $evidencia,
            'estado_evidencia_comercial' => $motivo !== 'sin_informacion' ? 'verificada_por_origen' : 'requiere_revision',
            'huella_comercial' => hash('sha256', json_encode($huella)),
        ];
    }

    /** Datos para consulta de quien concilia. El anexo respalda solo el concepto que registra. */
    private static function evidenciaComercial(PedidoBma $pedido, ?PedidoBmaCumplimientoFisico $cumplimiento): array
    {
        $tarea = $cumplimiento?->tarea;
        $caratulas = collect($pedido->getRelationValue('caratulasComision'))
            ->filter(fn (PedidoBmaCaratula $fila) => $fila->esVigente() && (! $tarea || $fila->pedido_bma_tarea_preparacion_id === $tarea->id));

        return [
            'modalidad_tarea' => $tarea?->modalidad_cobro,
            'caratulas' => $caratulas->unique('pedido_bma_tarea_preparacion_id')->values()->map(fn ($caratula) => [
                'id' => $caratula->id,
                'modalidad' => $caratula->modalidad_cobro,
                'remision_id' => $caratula->documento_remision_id,
                'paqueteria_id' => $caratula->catalogo_paqueteria_id,
            ])->all(),
            'anexos' => $pedido->anexosEnvio->map(fn (PedidoBmaAnexoEnvio $anexo) => [
                'id' => $anexo->id,
                'concepto' => $anexo->tipo,
                'monto' => $anexo->monto,
                'estado' => $anexo->estatus,
                'comprobante' => filled($anexo->ruta_archivo),
                'validador_id' => $anexo->validado_por_id,
                'validado_at' => $anexo->validado_at?->toIso8601String(),
            ])->sortBy('id')->values()->all(),
            'validacion_salida' => $cumplimiento?->pago_confirmado_at ? [
                'condicion' => $cumplimiento->condicion_cobro,
                'pago_id' => $cumplimiento->pedido_bma_pago_id,
                'folio_operacion' => $cumplimiento->folio_operacion,
            ] : null,
        ];
    }

    public static function desgloseAgrupado(PedidoBmaCumplimientoFisico $cumplimiento): ?array
    {
        if ($cumplimiento->cajas_empaque === null || $cumplimiento->bolsas_empaque === null
            || $cumplimiento->cajas_empaque + $cumplimiento->bolsas_empaque !== $cumplimiento->bultos_salida) {
            return null;
        }

        return ['cajas' => $cumplimiento->cajas_empaque, 'bolsas' => $cumplimiento->bolsas_empaque];
    }

    /**
     * Hecho revisado de una cancelación. No es la huella de la captura manual:
     * esa describe el registro y no cambia cuando el pedido se cancela otra vez.
     */
    public static function deCancelacion(int $pedidoId, mixed $canceladoAt): string
    {
        return hash('sha256', json_encode([
            'hecho' => 'cancelacion',
            'pedido_bma_id' => $pedidoId,
            'cancelado_at' => (string) $canceladoAt,
        ]));
    }
}
