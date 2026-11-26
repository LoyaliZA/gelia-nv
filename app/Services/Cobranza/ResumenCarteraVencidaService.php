<?php

namespace App\Services\Cobranza;

use App\Models\Cliente;
use App\Models\CobranzaFactura;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ResumenCarteraVencidaService
{
    /**
     * @return array{
     *     saldo_vencido: float,
     *     cartera: array<string, array{cantidad: int, total: float}>,
     *     top_vencidos: list<array<string, mixed>>
     * }
     */
    public function ejecutar(?Carbon $hoy = null, int $limiteTop = 5): array
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();
        $cartera = self::carteraVacia();

        $facturas = CobranzaFactura::query()
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->whereDate('fecha_vencimiento', '<', $hoy->toDateString())
            ->get(['id', 'cliente_id', 'folio', 'monto', 'fecha_vencimiento']);

        $porCliente = [];

        foreach ($facturas as $factura) {
            $atraso = (int) Carbon::parse($factura->fecha_vencimiento)->startOfDay()->diffInDays($hoy, false);
            if ($atraso < 1) {
                continue;
            }

            $clave = $this->claveRango($atraso);
            $cartera[$clave]['cantidad']++;
            $cartera[$clave]['total'] += (float) $factura->monto;

            $clienteId = (int) $factura->cliente_id;
            if (! isset($porCliente[$clienteId])) {
                $porCliente[$clienteId] = [
                    'cliente_id' => $clienteId,
                    'saldo_vencido' => 0.0,
                    'dias_atraso' => 0,
                    'folios' => 0,
                    'folio_mas_viejo' => $factura->folio,
                ];
            }
            $porCliente[$clienteId]['saldo_vencido'] += (float) $factura->monto;
            $porCliente[$clienteId]['folios']++;
            if ($atraso > $porCliente[$clienteId]['dias_atraso']) {
                $porCliente[$clienteId]['dias_atraso'] = $atraso;
                $porCliente[$clienteId]['folio_mas_viejo'] = $factura->folio;
            }
        }

        foreach ($cartera as $clave => $rango) {
            $cartera[$clave]['total'] = round($rango['total'], 2);
        }

        $saldo = round((float) $facturas->sum('monto'), 2);
        $top = $this->ordenarTop($porCliente, $limiteTop);

        return [
            'saldo_vencido' => $saldo,
            'cartera' => $cartera,
            'top_vencidos' => $top,
        ];
    }

    /**
     * @return array<string, array{cantidad: int, total: float}>
     */
    public static function carteraVacia(): array
    {
        return [
            'rango_1_30' => ['cantidad' => 0, 'total' => 0.0],
            'rango_31_60' => ['cantidad' => 0, 'total' => 0.0],
            'rango_61_90' => ['cantidad' => 0, 'total' => 0.0],
            'rango_91_120' => ['cantidad' => 0, 'total' => 0.0],
            'rango_120_mas' => ['cantidad' => 0, 'total' => 0.0],
        ];
    }

    private function claveRango(int $atraso): string
    {
        if ($atraso <= 30) {
            return 'rango_1_30';
        }
        if ($atraso <= 60) {
            return 'rango_31_60';
        }
        if ($atraso <= 90) {
            return 'rango_61_90';
        }
        if ($atraso <= 120) {
            return 'rango_91_120';
        }

        return 'rango_120_mas';
    }

    /**
     * @param  array<int, array<string, mixed>>  $porCliente
     * @return list<array<string, mixed>>
     */
    private function ordenarTop(array $porCliente, int $limite): array
    {
        if ($porCliente === []) {
            return [];
        }

        uasort($porCliente, function (array $a, array $b) {
            return $b['saldo_vencido'] <=> $a['saldo_vencido'];
        });

        $seleccion = array_slice($porCliente, 0, $limite, true);
        $clientes = Cliente::query()
            ->whereIn('id', array_keys($seleccion))
            ->get(['id', 'nombre', 'numero_cliente'])
            ->keyBy('id');

        return Collection::make($seleccion)
            ->values()
            ->map(function (array $fila) use ($clientes) {
                $cliente = $clientes->get($fila['cliente_id']);

                return [
                    'cliente_id' => $fila['cliente_id'],
                    'nombre' => $cliente?->nombre ?? 'Cliente sin nombre',
                    'numero_cliente' => $cliente?->numero_cliente,
                    'saldo_vencido' => round((float) $fila['saldo_vencido'], 2),
                    'dias_atraso' => (int) $fila['dias_atraso'],
                    'folios' => (int) $fila['folios'],
                    'folio_mas_viejo' => $fila['folio_mas_viejo'],
                ];
            })
            ->all();
    }
}
