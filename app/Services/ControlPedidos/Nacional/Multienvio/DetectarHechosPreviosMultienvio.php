<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafPedidoAplicacion;

/**
 * Detecta, sin escribir nada, los hechos financieros, documentales u operativos que el origen ya tiene
 * y que impiden repartirlo en N envíos por el camino directo (R1 A.4).
 */
class DetectarHechosPreviosMultienvio
{
    /** Claves JSON que indican un hecho posterior a la consulta en el origen. */
    private const CLAVES_HECHO_PREVIO = [
        'pago_solo_mercancia_nacional', 'invalidacion_guia', 'vinculo_resguardo', 'complementos_nacionales',
        'cancelacion_nacional', 'cambio_a_tienda',
    ];

    /** @return array<string, bool> todas las claves siempre presentes */
    public function ejecutar(PedidoBma $origen): array
    {
        $json = $origen->solicitud_preparacion_json ?? [];
        $claveJson = false;
        foreach (self::CLAVES_HECHO_PREVIO as $clave) {
            $claveJson = $claveJson || ! empty($json[$clave]);
        }

        return [
            'pago_capturado' => PedidoBmaPago::query()->where('pedido_bma_id', $origen->id)->exists(),
            'pago_validado' => $origen->pago_validado_at !== null,
            'cierre_o_saf' => (float) $origen->saldo_a_favor > 0
                || SafPedidoAplicacion::query()->where('pedido_bma_id', $origen->id)->exists()
                || PedidoBmaCierrePago::query()->where('pedido_bma_id', $origen->id)->exists(),
            // La remisión también puede existir solo como PDF vigente (sin número capturado).
            'remision' => trim((string) $origen->numero_remision) !== '' || $origen->tieneRemision(),
            'guia' => trim((string) $origen->numero_rastreo) !== ''
                || $origen->guia_subida_at !== null
                || $origen->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->where('activo', true)->exists()
                || $origen->cajas()->activas()->whereNotNull('numero_rastreo')->where('numero_rastreo', '!=', '')->exists(),
            'empacada' => $origen->empacado_at !== null,
            'salida_parcial' => $origen->cajas()->activas()
                ->where('estatus_recoleccion', PedidoBmaCaja::ESTATUS_RECOLECTADA)->exists(),
            'cancelacion' => $origen->cancelacionOperativaActiva()->exists(),
            'complemento' => $claveJson || PedidoBma::query()->where('pedido_principal_id', $origen->id)->exists(),
        ];
    }

    /** @param  array<string, bool>  $hechos */
    public static function hayHechos(array $hechos): bool
    {
        return in_array(true, $hechos, true);
    }
}
