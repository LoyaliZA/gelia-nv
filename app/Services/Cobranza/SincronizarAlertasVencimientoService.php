<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaAlerta;
use App\Models\CobranzaFactura;
use Carbon\Carbon;

class SincronizarAlertasVencimientoService
{
    /**
     * Resuelve alertas de vencimiento obsoletas y sincroniza las de facturas vencidas activas.
     *
     * @return array{resueltas: int, creadas: int, actualizadas: int}
     */
    public function ejecutar(): array
    {
        $today = now()->toDateString();
        $hoy = now()->startOfDay();

        $resueltas = CobranzaAlerta::query()
            ->where('tipo', 'vencimiento')
            ->where('estado', '!=', 'resuelta')
            ->where(function ($query) {
                $query->whereDoesntHave('factura')
                    ->orWhereHas('factura', function ($factura) {
                        $factura->where('pagada', true)->orWhere('monto', '<=', 0);
                    });
            })
            ->update(['estado' => 'resuelta']);

        $creadas = 0;
        $actualizadas = 0;

        $facturasVencidas = CobranzaFactura::query()
            ->with('cliente')
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->where('fecha_vencimiento', '<', $today)
            ->get();

        foreach ($facturasVencidas as $factura) {
            $cliente = $factura->cliente;
            if (!$cliente) {
                continue;
            }

            $vencimiento = Carbon::parse($factura->fecha_vencimiento)->startOfDay();
            $diasAtraso = $vencimiento->diffInDays($hoy, false);

            if ($diasAtraso < 1) {
                continue;
            }

            $alertaActiva = CobranzaAlerta::query()
                ->where('factura_id', $factura->id)
                ->where('tipo', 'vencimiento')
                ->where('estado', '!=', 'resuelta')
                ->first();

            if (!$alertaActiva) {
                CobranzaAlerta::create([
                    'cliente_id' => $cliente->id,
                    'factura_id' => $factura->id,
                    'tipo' => 'vencimiento',
                    'dias_atraso' => $diasAtraso,
                    'fecha_alerta' => $today,
                    'estado' => 'pendiente',
                ]);
                $creadas++;
            } else {
                $alertaActiva->update([
                    'dias_atraso' => $diasAtraso,
                    'fecha_alerta' => $today,
                ]);
                $actualizadas++;
            }
        }

        return [
            'resueltas' => $resueltas,
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
        ];
    }
}
