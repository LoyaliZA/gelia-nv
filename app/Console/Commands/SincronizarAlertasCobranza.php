<?php

namespace App\Console\Commands;

use App\Models\CobranzaAlerta;
use App\Models\CobranzaFactura;
use App\Models\Cliente;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cobranza:sincronizar-alertas')]
#[Description('Resuelve alertas de límite superado obsoletas según el consolidado actual')]
class SincronizarAlertasCobranza extends Command
{
    public function handle(): int
    {
        $resueltas = 0;
        $creadas = 0;

        $alertasLimite = CobranzaAlerta::query()
            ->with(['cliente', 'factura'])
            ->where('tipo', 'limite_superado')
            ->where('estado', 'pendiente')
            ->get();

        foreach ($alertasLimite as $alerta) {
            $cliente = $alerta->cliente;
            if (!$cliente) {
                continue;
            }

            $limite = (float) $cliente->monto_credito_autorizado;
            $factura = $alerta->factura
                ?? CobranzaFactura::query()
                    ->where('cliente_id', $cliente->id)
                    ->where('pagada', false)
                    ->orderByDesc('monto')
                    ->first();

            $consolidado = (float) ($factura?->monto ?? 0);

            if ($limite <= 0 || $consolidado <= $limite) {
                $alerta->update(['estado' => 'resuelta']);
                $resueltas++;
                $this->line("Resuelta alerta #{$alerta->id} — cliente {$cliente->numero_cliente} (consolidado \${$consolidado} <= límite \${$limite})");
            }
        }

        $clientesConCredito = Cliente::query()
            ->with('facturaCobranzaActiva')
            ->whereNotNull('monto_credito_autorizado')
            ->where('monto_credito_autorizado', '>', 0)
            ->whereHas('facturasCobranza', fn ($q) => $q->where('pagada', false)->where('monto', '>', 0))
            ->get();

        foreach ($clientesConCredito as $cliente) {
            $limite = (float) $cliente->monto_credito_autorizado;
            $consolidado = (float) ($cliente->facturaCobranzaActiva?->monto ?? 0);

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
                    'factura_id' => $cliente->facturaCobranzaActiva?->id,
                    'tipo' => 'limite_superado',
                    'dias_atraso' => null,
                    'fecha_alerta' => now()->toDateString(),
                    'estado' => 'pendiente',
                ]);
                $creadas++;
                $this->line("Creada alerta — cliente {$cliente->numero_cliente} (consolidado \${$consolidado} > límite \${$limite})");
            }
        }

        $this->info("Sincronización completada. Resueltas: {$resueltas}, creadas: {$creadas}.");

        return self::SUCCESS;
    }
}
