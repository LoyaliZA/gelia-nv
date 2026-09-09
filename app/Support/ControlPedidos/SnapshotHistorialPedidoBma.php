<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;

/**
 * Snapshots estructurados para bitácora (evidencia de lo capturado en cada paso).
 */
final class SnapshotHistorialPedidoBma
{
    /** @return array<string, mixed> */
    public static function financiero(PedidoBma $pedido): array
    {
        return [
            'financiero' => [
                'folio' => $pedido->folio,
                'folio_remision' => $pedido->folio_remision,
                'total_mercancia' => self::m($pedido->total_mercancia),
                'costo_envio' => self::m($pedido->costo_envio),
                'costo_seguro' => self::m($pedido->costo_seguro),
                'aplica_seguro' => (bool) $pedido->aplica_seguro,
                'saldo_a_favor' => self::m($pedido->saldo_a_favor),
                'total_a_cobrar' => self::m($pedido->total_a_cobrar),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function cobertura(PedidoBma $pedido): array
    {
        $resumen = app(CoberturaPagoPedidoBmaService::class)->calcular($pedido);

        return [
            'cobertura' => [
                'total_a_cubrir' => $resumen['total_a_cubrir'] ?? null,
                'total_a_cobrar' => $resumen['total_a_cobrar'] ?? null,
                'pagos_validos' => $resumen['pagos_validos'] ?? null,
                'diferencia' => $resumen['diferencia'] ?? null,
                'cobertura' => $resumen['cobertura'] ?? null,
                'tolerancia_aplicada' => $resumen['tolerancia_aplicada'] ?? null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function exhibicion(PedidoBmaPago $pago, ?PedidoBma $pedido = null): array
    {
        $pago->loadMissing('banco');

        $snap = [
            'exhibicion' => [
                'numero' => (int) $pago->numero_exhibicion,
                'monto' => self::m($pago->monto),
                'forma_pago' => $pago->forma_pago,
                'forma_label' => PedidoBmaPago::labelForma($pago->forma_pago),
                'banco' => $pago->banco?->nombre,
                'referencia' => $pago->referencia,
                'estado_revision' => $pago->estado_revision,
            ],
        ];

        if ($pago->ruta_archivo) {
            $snap['archivos'] = [[
                'tipo' => 'comprobante_pago',
                'ruta' => $pago->ruta_archivo,
                'nombre' => $pago->nombre_original,
                'mime_type' => $pago->mime_type,
            ]];
        }

        if ($pedido) {
            $snap = self::merge($snap, self::financiero($pedido), self::cobertura($pedido));
        }

        return $snap;
    }

    /**
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    public static function errorReportado(array $campos, string $detalle = ''): array
    {
        return [
            'error' => [
                'campos' => $campos,
                'campos_etiquetas' => CamposIncorrectosPedidoBma::etiquetasDe($campos),
                'detalle' => $detalle !== '' ? $detalle : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function archivo(string $tipo, string $ruta, ?string $nombre = null, ?string $mime = null): array
    {
        return [
            'archivos' => [[
                'tipo' => $tipo,
                'ruta' => $ruta,
                'nombre' => $nombre,
                'mime_type' => $mime,
            ]],
        ];
    }

    /** @param  array<string, mixed>  ...$partes */
    public static function merge(array ...$partes): array
    {
        $out = [];
        foreach ($partes as $parte) {
            foreach ($parte as $clave => $valor) {
                if ($clave === 'archivos' && isset($out['archivos']) && is_array($valor)) {
                    $out['archivos'] = array_values(array_merge($out['archivos'], $valor));

                    continue;
                }
                $out[$clave] = $valor;
            }
        }

        return $out;
    }

    private static function m(mixed $valor): string
    {
        return number_format((float) ($valor ?? 0), 2, '.', '');
    }
}
