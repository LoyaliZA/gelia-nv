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

        $contadorAlertasVencimiento = 0;
        $contadorAlertasLimite = 0;

        foreach ($facturasActivas as $factura) {
            $cliente = $factura->cliente;
            if (!$cliente) {
                continue;
            }

            // Días de atraso = hoy - fecha_vencimiento
            $vencimiento = \Carbon\Carbon::parse($factura->fecha_vencimiento)->startOfDay();
            $hoy = now()->startOfDay();
            $diasAtraso = $vencimiento->diffInDays($hoy, false);

            if ($diasAtraso >= 1) {
                $alertaPendiente = \App\Models\CobranzaAlerta::where('factura_id', $factura->id)
                    ->where('tipo', 'vencimiento')
                    ->where('estado', 'pendiente')
                    ->first();

                $alertaExistenteMismoDia = \App\Models\CobranzaAlerta::where('factura_id', $factura->id)
                    ->where('tipo', 'vencimiento')
                    ->where('dias_atraso', $diasAtraso)
                    ->exists();

                $alertaParaNotificar = null;

                if (!$alertaPendiente && !$alertaExistenteMismoDia) {
                    $alertaParaNotificar = \App\Models\CobranzaAlerta::create([
                        'cliente_id' => $cliente->id,
                        'factura_id' => $factura->id,
                        'tipo' => 'vencimiento',
                        'dias_atraso' => $diasAtraso,
                        'fecha_alerta' => $today,
                        'estado' => 'pendiente',
                    ]);
                    $contadorAlertasVencimiento++;
                } elseif ($alertaPendiente) {
                    $alertaPendiente->update(['dias_atraso' => $diasAtraso, 'fecha_alerta' => $today]);
                    $alertaParaNotificar = $alertaPendiente;
                }

                // Notificar a los administradores / superadmins y al vendedor del cliente SOLO en días específicos
                if ($alertaParaNotificar && in_array($diasAtraso, [3, 6, 9, 12])) {
                    $mensajeVoz = "Cliente {$cliente->nombre} tiene un saldo vencido hace {$diasAtraso} días";
                    
                    $usuariosANotificar = \App\Models\User::role(['Super Admin', 'Administrador'])->get();
                    if ($cliente->vendedor) {
                        $usuariosANotificar->push($cliente->vendedor);
                    }

                    $usuariosANotificar = $usuariosANotificar->unique('id');

                    foreach ($usuariosANotificar as $user) {
                        // Evitamos duplicados si el sistema permite
                        $user->notify(new \App\Notifications\AlertaCobranza($alertaParaNotificar, $mensajeVoz));
                    }
                }
            }
        }

        $this->info("Evaluando límites de crédito...");
        $clientesConCredito = \App\Models\Cliente::with('facturaCobranzaActiva')
            ->whereNotNull('monto_credito_autorizado')
            ->where('monto_credito_autorizado', '>', 0)
            ->get();

        $alertasLimiteExcedidoMasivo = [];

        foreach ($clientesConCredito as $cliente) {
            $limiteFinal = (float) $cliente->monto_credito_autorizado;
            $montoFinal = (float) $cliente->monto_venta_actual;
            $consolidado = (float) ($cliente->facturaCobranzaActiva?->monto ?? 0);
            
            $deudaReal = max($montoFinal, $consolidado);

            if ($limiteFinal > 0 && $deudaReal > $limiteFinal) {
                $alertaPendiente = \App\Models\CobranzaAlerta::where('cliente_id', $cliente->id)
                    ->where('tipo', 'limite_superado')
                    ->where('estado', 'pendiente')
                    ->first();

                if (!$alertaPendiente) {
                    \App\Models\CobranzaAlerta::create([
                        'cliente_id' => $cliente->id,
                        'factura_id' => $cliente->facturaCobranzaActiva?->id,
                        'tipo' => 'limite_superado',
                        'dias_atraso' => null,
                        'fecha_alerta' => $today,
                        'estado' => 'pendiente',
                    ]);
                    $contadorAlertasLimite++;
                    
                    $alertasLimiteExcedidoMasivo[] = [
                        'cliente' => $cliente,
                        'monto_actual' => $deudaReal,
                        'limite' => $limiteFinal
                    ];
                }
            }
        }

        if (!empty($alertasLimiteExcedidoMasivo)) {
            $this->info("Notificando a administradores sobre límites excedidos...");
            $usuariosNotificar = \App\Models\User::permission('cobranza.recibir_alertas')->get();
            $superAdmins = \App\Models\User::role('Super Admin')->get();
            $todosLosUsuarios = $usuariosNotificar->merge($superAdmins)->unique('id');

            if ($todosLosUsuarios->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send(
                    $todosLosUsuarios, 
                    new \App\Notifications\AlertaLimiteCreditoSuperadoMasivoNotification($alertasLimiteExcedidoMasivo)
                );
            }
        }

        $this->info("Evaluación finalizada. Alertas Vencimiento: {$contadorAlertasVencimiento}, Alertas Límite: {$contadorAlertasLimite}");
    }
}
