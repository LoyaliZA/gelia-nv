<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Support\Collection;

final class GuiasPdfPorCaja
{
    public const AVANCE = 'guia_caja_avance';
    public const CONFIRMADA = 'guia_caja_confirmada';
    public const DIRECTORIO = 'pedidos_bma/guias_por_caja/';

    public static function aplica(PedidoBma $pedido): bool
    {
        return CapturaNacional::es($pedido)
            && ! $pedido->aplica_seguro
            && ! $pedido->cliente_proporciona_guia
            && $pedido->ofreceRastreo();
    }

    public static function pendientes(PedidoBma $pedido): Collection
    {
        $pedido->loadMissing('cajas');

        return $pedido->cajas->filter(fn (PedidoBmaCaja $caja) => $caja->estaActiva() && $caja->estaPendiente());
    }

    public static function vigentes(PedidoBma $pedido): Collection
    {
        $pedido->loadMissing('documentos');

        return $pedido->documentos->filter(fn (PedidoBmaDocumento $doc) => $doc->tipo === PedidoBmaDocumento::TIPO_GUIA
            && $doc->activo && in_array($doc->relacion_tipo, [self::AVANCE, self::CONFIRMADA], true));
    }

    public static function faltantes(Collection $cajas, Collection $guias): int
    {
        $porCaja = $guias->groupBy('pedido_bma_caja_id');

        return $cajas->filter(fn (PedidoBmaCaja $caja) => $porCaja->get($caja->id)?->count() !== 1)->count();
    }

    public static function presentarEnCedis(PedidoBma $pedido): void
    {
        if (! self::aplica($pedido)) {
            return;
        }

        $pendientes = self::pendientes($pedido);
        $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $caja) => $caja->estaActiva())->pluck('id');
        $guias = self::vigentes($pedido)->where('relacion_tipo', self::CONFIRMADA)->whereIn('pedido_bma_caja_id', $activas);
        $pedido->setAttribute('guias_pdf_por_caja', $pendientes->isNotEmpty() || $guias->isNotEmpty());
        $pedido->setAttribute('faltan_guias_por_caja', self::faltantes($pendientes, $guias));
        $pedido->setRelation('documentos', $pedido->documentos->reject(function (PedidoBmaDocumento $doc) use ($guias, $pendientes) {
            if ($doc->tipo !== PedidoBmaDocumento::TIPO_GUIA) {
                return false;
            }

            return ! $guias->contains('id', $doc->id)
                && ! ($pendientes->isEmpty() && $guias->isEmpty() && $doc->relacion_tipo === null);
        })->values());
    }
}
