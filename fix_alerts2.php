<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cliente;
use App\Models\CobranzaAlerta;

$clientes = Cliente::whereHas('facturasActivas')->get();
foreach ($clientes as $cliente) {
    $factura = $cliente->facturasActivas()->first();
    if ($factura) {
        $limite = (float) $cliente->monto_credito_autorizado;
        if ($limite > 0 && $factura->monto <= $limite) {
            $alertas = CobranzaAlerta::where('cliente_id', $cliente->id)
                ->where('tipo', 'limite_superado')
                ->where('estado', '!=', 'resuelta')
                ->get();
            foreach ($alertas as $alerta) {
                $alerta->update(['estado' => 'resuelta']);
                echo "Alerta {$alerta->id} resuelta para cliente {$cliente->id}\n";
            }
        }
    }
}
echo "Done2\n";
