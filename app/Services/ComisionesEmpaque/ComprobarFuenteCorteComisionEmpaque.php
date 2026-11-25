<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaBultoEmpaque;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Support\ComisionesEmpaque\FuentesComisionEmpaque;
use App\Support\ComisionesEmpaque\HuellaFuenteComisionEmpaque;

/**
 * Relee solo las fuentes de las unidades de la vista previa.
 * No importa el histórico ni escribe en pedidos.
 * Un cambio posterior a la confirmación no reescribe el corte: queda como incidencia.
 */
class ComprobarFuenteCorteComisionEmpaque
{
    public function diferencia(UnidadComisionEmpaque $unidad): ?string
    {
        if (in_array($unidad->origen_registro, ['manual', 'carga'], true)) {
            if (! $unidad->pedido_bma_id) {
                return null;
            }
            $pedido = PedidoBma::query()->find($unidad->pedido_bma_id);
            if (! $pedido) {
                return 'No se pudo comprobar el pedido vinculado de '.$unidad->clave_origen.'.';
            }
            if ($pedido->cancelado_at) {
                $hecho = HuellaFuenteComisionEmpaque::deCancelacion((int) $pedido->id, $pedido->cancelado_at);
                if ($unidad->ancla_decision === 'conservar'
                    && $unidad->ancla_huella
                    && hash_equals((string) $unidad->ancla_huella, $hecho)) {
                    return null;
                }

                return 'El pedido '.$unidad->folio_pedido.' se canceló en el origen. Actualice y revise el cálculo antes de confirmar.';
            }

            return null;
        }

        if (UnidadComisionEmpaque::fuenteYaEnCorte($unidad->origen_tipo, $unidad->origen_id, $unidad->id)) {
            return 'El mismo empaque ya pertenece a otro corte. Revise si corresponde un ajuste.';
        }
        $viva = $this->fuenteViva($unidad);
        if (! $viva || $unidad->huella !== $viva['huella']) {
            return 'La fuente de '.$unidad->clave_origen.' cambió o dejó de estar activa. Pulse Actualizar y revise el empaque.';
        }
        if (($unidad->logistica['huella_comercial'] ?? null) !== $viva['comercial']) {
            return 'Cambió la información comercial de '.$unidad->clave_origen.'. Pulse Actualizar y revise la conciliación.';
        }

        return null;
    }

    private function fuenteViva(UnidadComisionEmpaque $unidad): ?array
    {
        $procedencia = $unidad->procedencia;
        $municipalTienda = $procedencia === FuentesComisionEmpaque::TIENDA_MUNICIPAL;
        if ($municipalTienda) {
            $procedencia = match ($unidad->origen_tipo) {
                'pedido_bma_cajas' => FuentesComisionEmpaque::CEDIS_CAJA,
                'pedido_bma_bultos_empaque' => FuentesComisionEmpaque::CEDIS_BULTO,
                default => FuentesComisionEmpaque::CEDIS_CABECERA,
            };
        }

        $caja = null;
        $cumplimiento = null;
        $tipo = null;
        $desglose = null;
        switch ($procedencia) {
            case FuentesComisionEmpaque::CEDIS_CAJA:
                $caja = PedidoBmaCaja::query()->with(['pedido', 'remisionesActivas.pedido:id,numero_remision,folio_remision'])->find($unidad->origen_id);
                if (! $caja?->estaActiva()) {
                    return null;
                }
                $pedido = $caja->pedido;
                $fecha = $pedido?->empacado_at;
                $cantidad = max(1, (int) $caja->cantidad);
                $tipo = $caja->catalogo_tipo_caja_id;
                break;
            case FuentesComisionEmpaque::CEDIS_BULTO:
                $bulto = PedidoBmaBultoEmpaque::query()->with('pedido')->find($unidad->origen_id);
                $pedido = $bulto?->pedido;
                $fecha = $pedido?->empacado_at ? ($bulto->empacado_at ?: $pedido->empacado_at) : null;
                $cantidad = 1;
                $desglose = $bulto?->tipo_bulto;
                if ($pedido?->cajas()->activas()->exists()) {
                    return null;
                }
                break;
            case FuentesComisionEmpaque::CEDIS_CABECERA:
                $pedido = PedidoBma::query()->find($unidad->origen_id);
                $fecha = $pedido?->empacado_at;
                $cantidad = (int) $pedido?->numero_cajas;
                if ($pedido?->cajas()->activas()->exists() || $pedido?->bultosEmpaque()->exists()) {
                    return null;
                }
                break;
            case FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO:
                $cumplimiento = PedidoBmaCumplimientoFisico::query()->with('tarea.pedido')->find($unidad->origen_id);
                $pedido = $cumplimiento?->tarea?->pedido;
                $fecha = $cumplimiento?->empacado_at;
                $cantidad = (int) $cumplimiento?->bultos_salida;
                $desglose = $cumplimiento ? HuellaFuenteComisionEmpaque::desgloseAgrupado($cumplimiento) : null;
                break;
            default:
                return null;
        }
        if (! $pedido || (int) $pedido->id !== (int) $unidad->pedido_bma_id || ! $fecha || $cantidad < 1) {
            return null;
        }
        if ($procedencia !== FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO
            && $municipalTienda !== HuellaFuenteComisionEmpaque::empaqueMunicipalEnTienda($pedido)) {
            return null;
        }
        if ($municipalTienda && $pedido->tareasPreparacion()->whereHas('cumplimientoFisico', fn ($q) => $q->whereNotNull('empacado_at')->where('bultos_salida', '>', 0))->exists()) {
            return null;
        }
        if ($procedencia === FuentesComisionEmpaque::CEDIS_CABECERA
            && ($operacion = $pedido->miembroOperacionEmpaque?->operacion_empaque_id)
            && \App\Models\ControlPedidos\OperacionEmpaqueMiembro::query()
                ->where('operacion_empaque_id', $operacion)->whereHas('pedido.cajas', fn ($q) => $q->activas())->exists()) {
            return null;
        }

        return [
            'huella' => HuellaFuenteComisionEmpaque::de($cantidad, $fecha, $pedido->cancelado_at, $tipo, $desglose),
            'comercial' => HuellaFuenteComisionEmpaque::datosComerciales($pedido, $caja, $cumplimiento)['huella_comercial'],
        ];
    }
}
