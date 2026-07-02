<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaAlerta;
use App\Models\CobranzaFactura;
use App\Models\Cliente;

class SincronizarAlertasLimiteService
{
    /**
     * Resuelve alertas de límite obsoletas, actualiza factura_id y crea las faltantes.
     *
     * @return array{resueltas: int, creadas: int, actualizadas: int}
     */
    public function ejecutar(): array
    {
        $resueltas = 0;
        $creadas = 0;
        $actualizadas = 0;

        $alertasLimite = CobranzaAlerta::query()
            ->with(['cliente.facturasCobranzaActivas', 'factura'])
            ->where('tipo', 'limite_superado')
            ->where('estado', 'pendiente')
            ->get();

        foreach ($alertasLimite as $alerta) {
            $cliente = $alerta->cliente;
            if (!$cliente) {
                continue;
            }

            $factura = $alerta->factura;
            if (!$factura || $factura->pagada || (float) $factura->monto <= 0) {
                $factura = $cliente->factura_cobranza_critica
                    ?? $cliente->facturasCobranzaActivas->first();

                if ($factura && $alerta->factura_id !== $factura->id) {
                    $alerta->update(['factura_id' => $factura->id]);
                    $actualizadas++;
                }
            }

            $limite = (float) $cliente->monto_credito_autorizado;
            $consolidado = (float) CobranzaFactura::query()
                ->where('cliente_id', $cliente->id)
                ->where('pagada', false)
                ->where('monto', '>', 0)
                ->sum('monto');

            if ($limite <= 0 || $consolidado <= $limite) {
                $alerta->update(['estado' => 'resuelta']);
                $resueltas++;
            }
        }

        $clientesConCredito = Cliente::query()
            ->with('facturasCobranzaActivas')
            ->whereNotNull('monto_credito_autorizado')
            ->where('monto_credito_autorizado', '>', 0)
            ->whereHas('facturasCobranza', fn ($q) => $q->where('pagada', false)->where('monto', '>', 0))
            ->get();

        foreach ($clientesConCredito as $cliente) {
            $limite = (float) $cliente->monto_credito_autorizado;
            $consolidado = (float) $cliente->saldo_total_pendiente;

            if ($consolidado <= $limite) {
                continue;
            }

            $existe = CobranzaAlerta::query()
                ->where('cliente_id', $cliente->id)
                ->where('tipo', 'limite_superado')
                ->where('estado', 'pendiente')
                ->exists();

            if (!$existe) {
                CobranzaAlerta::create([
                    'cliente_id' => $cliente->id,
                    'factura_id' => $cliente->factura_cobranza_critica?->id ?? $cliente->facturasCobranzaActivas->first()?->id,
                    'tipo' => 'limite_superado',
                    'dias_atraso' => null,
                    'fecha_alerta' => now()->toDateString(),
                    'estado' => 'pendiente',
                ]);
                $creadas++;
            }
        }

        return [
            'resueltas' => $resueltas,
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
        ];
    }
}
