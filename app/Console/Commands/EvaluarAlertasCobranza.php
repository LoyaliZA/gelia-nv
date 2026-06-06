<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cobranza:evaluar-alertas')]
#[Description('Command description')]
class EvaluarAlertasCobranza extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();
        $this->info("Iniciando evaluación de cobranza para la fecha: {$today}");

        $facturasActivas = \App\Models\CobranzaFactura::with('cliente')
            ->where('pagada', false)
            ->get();

        $contadorAlertas = 0;

        foreach ($facturasActivas as $factura) {
            $cliente = $factura->cliente;
            if (!$cliente) {
                continue;
            }

            // Días de atraso = hoy - fecha_vencimiento
            $vencimiento = \Carbon\Carbon::parse($factura->fecha_vencimiento)->startOfDay();
            $hoy = now()->startOfDay();
            $diasAtraso = $vencimiento->diffInDays($hoy, false);

            $this->info("Factura {$factura->folio} de Cliente {$cliente->nombre} tiene {$diasAtraso} días de atraso.");

            // Detonar alertas estrictamente en los días 3, 6, 9 y 12
            if (in_array($diasAtraso, [3, 6, 9, 12])) {
                $existeAlerta = \App\Models\CobranzaAlerta::where('factura_id', $factura->id)
                    ->where('dias_atraso', $diasAtraso)
                    ->exists();

                if (!$existeAlerta) {
                    $alerta = \App\Models\CobranzaAlerta::create([
                        'cliente_id' => $cliente->id,
                        'factura_id' => $factura->id,
                        'dias_atraso' => $diasAtraso,
                        'fecha_alerta' => $today,
                        'estado' => 'pendiente',
                    ]);

                    $contadorAlertas++;

                    // Notificar a los administradores / superadmins y al vendedor del cliente
                    $mensajeVoz = "Cliente {$cliente->nombre} tiene un saldo vencido hace {$diasAtraso} días";
                    
                    $usuariosANotificar = \App\Models\User::role(['Super Admin', 'Administrador'])->get();
                    if ($cliente->vendedor) {
                        $usuariosANotificar->push($cliente->vendedor);
                    }

                    $usuariosANotificar = $usuariosANotificar->unique('id');

                    foreach ($usuariosANotificar as $user) {
                        $user->notify(new \App\Notifications\AlertaCobranza($alerta, $mensajeVoz));
                    }
                }
            }
        }

        $this->info("Evaluación finalizada. Alertas generadas: {$contadorAlertas}");
    }
}
