<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cliente;
use App\Models\CobranzaFactura;
use App\Models\CobranzaAlerta;

// 1. Limpiar facturas duplicadas
$clientesIds = CobranzaFactura::where('pagada', false)->pluck('cliente_id')->unique();
foreach ($clientesIds as $cId) {
    $activas = CobranzaFactura::where('cliente_id', $cId)->where('pagada', false)->orderBy('id', 'desc')->get();
    if ($activas->count() > 1) {
        $mantener = $activas->first();
        foreach ($activas as $activa) {
            if ($activa->id !== $mantener->id) {
                $activa->update(['pagada' => true, 'tiene_abono' => true]);
                echo "Marcada como pagada factura extra {$activa->id} para cliente {$cId}\n";
            }
        }
    }
}

// 2. Sincronizar monto_venta_actual y resolver alertas si ya bajaron del límite
$clientes = Cliente::whereHas('facturasActivas')->get();
foreach ($clientes as $cliente) {
    $factura = $cliente->facturasActivas()->first();
    if ($factura) {
        $cliente->update(['monto_venta_actual' => $factura->monto]);
        echo "Actualizado monto cliente {$cliente->id} a {$factura->monto}\n";
        
        $limite = (float) $cliente->monto_credito_autorizado;
        if ($limite > 0 && $factura->monto <= $limite) {
            $alertas = CobranzaAlerta::where('cliente_id', $cliente->id)
                ->where('tipo', 'limite_superado')
                ->where('estado', 'pendiente')
                ->get();
            foreach ($alertas as $alerta) {
                $alerta->update(['estado' => 'resuelta']);
                echo "Alerta {$alerta->id} resuelta para cliente {$cliente->id}\n";
            }
        }
    }
}
echo "Done\n";
