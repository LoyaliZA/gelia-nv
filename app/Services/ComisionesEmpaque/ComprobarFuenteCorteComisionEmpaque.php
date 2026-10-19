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
        if ($unidad->origen_registro === 'manual') {
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

        $viva = $this->huellaViva($unidad);
        if ($viva === null) {
            return 'No se pudo comprobar la fuente de '.$unidad->clave_origen.'. No se confirma con la información anterior.';
        }
        if (! hash_equals((string) $unidad->huella, $viva)) {
            return 'La fuente de '.$unidad->clave_origen.' cambió (cantidad, fecha, cancelación o tipo). Pulse Actualizar y revise el cálculo antes de confirmar.';
        }

        return null;
    }

    private function huellaViva(UnidadComisionEmpaque $unidad): ?string
    {
        if ($unidad->procedencia === FuentesComisionEmpaque::CEDIS_CAJA) {
            $caja = PedidoBmaCaja::query()->with('pedido')->find($unidad->origen_id);
            if (! $caja || ! $caja->estaActiva() || ! $caja->pedido?->empacado_at) {
                return hash('sha256', 'ausente');
            }

            return HuellaFuenteComisionEmpaque::de(
                max(1, (int) $caja->cantidad),
                $caja->pedido->empacado_at,
                $caja->pedido->cancelado_at,
                $caja->catalogo_tipo_caja_id
            );
        }

        if ($unidad->procedencia === FuentesComisionEmpaque::CEDIS_BULTO) {
            $bulto = PedidoBmaBultoEmpaque::query()->find($unidad->origen_id);
            $pedido = $bulto ? PedidoBma::query()->find($bulto->pedido_bma_id) : null;
            if (! $bulto || ! $pedido?->empacado_at) {
                return hash('sha256', 'ausente');
            }

            return HuellaFuenteComisionEmpaque::de(1, $pedido->empacado_at, $pedido->cancelado_at, null);
        }

        if ($unidad->procedencia === FuentesComisionEmpaque::CEDIS_CABECERA) {
            $pedido = PedidoBma::query()->find($unidad->origen_id);
            if (! $pedido?->empacado_at || (int) $pedido->numero_cajas < 1) {
                return hash('sha256', 'ausente');
            }

            return HuellaFuenteComisionEmpaque::de((int) $pedido->numero_cajas, $pedido->empacado_at, $pedido->cancelado_at, null);
        }

        if ($unidad->procedencia === FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO) {
            $cumplimiento = PedidoBmaCumplimientoFisico::query()->with('tarea.pedido')->find($unidad->origen_id);
            $pedido = $cumplimiento?->tarea?->pedido;
            if (! $cumplimiento?->empacado_at || ! $pedido || (int) $cumplimiento->bultos_salida < 1) {
                return hash('sha256', 'ausente');
            }

            return HuellaFuenteComisionEmpaque::de(
                (int) $cumplimiento->bultos_salida,
                $cumplimiento->empacado_at,
                $pedido->cancelado_at,
                null
            );
        }

        return null;
    }
}
