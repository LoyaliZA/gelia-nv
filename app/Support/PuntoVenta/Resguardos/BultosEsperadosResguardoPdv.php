<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaBultoEmpaque;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;

final class BultosEsperadosResguardoPdv
{
    /**
     * @return list<array{folio: string, tipo: string, piezas: int, numero_cedis: int|null}>
     */
    public static function desdeResguardo(ResguardoPdv $resguardo): array
    {
        $resguardo->loadMissing(['pedido.bultosEmpaque']);
        $pedido = $resguardo->pedido;
        $cantidad = max(1, (int) $resguardo->cantidad_bultos_esperada);
        $piezasTotales = max($cantidad, (int) ($pedido?->cantidad_piezas ?? $cantidad));
        $piezasPorBulto = self::repartirPiezas($piezasTotales, $cantidad);

        if ($pedido instanceof PedidoBma && $pedido->bultosEmpaque->isNotEmpty()) {
            return $pedido->bultosEmpaque
                ->sortBy('numero')
                ->values()
                ->map(function (PedidoBmaBultoEmpaque $bulto, int $indice) use ($pedido, $piezasPorBulto) {
                    return [
                        'folio' => GeneradorFolioBultoResguardoPdv::desdeNumero($pedido, (int) $bulto->numero),
                        'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                        'piezas' => $piezasPorBulto[$indice] ?? 1,
                        'numero_cedis' => (int) $bulto->numero,
                    ];
                })
                ->all();
        }

        $baseFolio = trim((string) ($resguardo->snapshot_folio ?: 'BULTO'));

        return array_map(function (int $indice) use ($baseFolio, $piezasPorBulto) {
            $numero = $indice + 1;

            return [
                'folio' => "{$baseFolio}-B{$numero}",
                'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                'piezas' => $piezasPorBulto[$indice] ?? 1,
                'numero_cedis' => null,
            ];
        }, range(0, $cantidad - 1));
    }

    /**
     * @return list<int>
     */
    private static function repartirPiezas(int $total, int $bultos): array
    {
        $bultos = max(1, $bultos);
        $total = max($bultos, $total);
        $base = intdiv($total, $bultos);
        $resto = $total % $bultos;
        $resultado = [];

        for ($i = 0; $i < $bultos; $i++) {
            $resultado[] = $base + ($i < $resto ? 1 : 0);
        }

        return $resultado;
    }
}
