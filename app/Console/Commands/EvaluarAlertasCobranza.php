<?php

namespace App\Console\Commands;

use App\Services\Cobranza\CobranzaAlertasReglasService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cobranza:evaluar-alertas')]
#[Description('Command description')]
class EvaluarAlertasCobranza extends Command
{
    public function __construct(
        private CobranzaAlertasReglasService $reglas,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();
        $this->info("Iniciando evaluación de cobranza para la fecha: {$today}");

        $configuracionAlertas = $this->reglas->normalizar(
            \Illuminate\Support\Facades\Cache::rememberForever('cobranza_config_alertas', function () {
                $config = \App\Models\CobranzaConfiguracion::where('llave', 'config_alertas')->first();
                return $config?->valor ?? [];
            })
        );

        $esDiaHabil = $this->reglas->esDiaHabil(now(), $configuracionAlertas);
        if (!$esDiaHabil) {
            $this->info('Hoy no es día hábil configurado; se omiten notificaciones de vencimiento.');
        }

        $facturasActivas = \App\Models\CobranzaFactura::with('cliente')
            ->where('pagada', false)
            ->get();

        $contadorAlertasVencimiento = 0;
        $contadorAlertasLimite = 0;
        $clientesVencidosParaNotificar = [];

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

                if (!$alertaPendiente && !$alertaExistenteMismoDia) {
                    \App\Models\CobranzaAlerta::create([
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
                }

                if (
                    $esDiaHabil
                    && $this->reglas->esDiaDeLlamada($diasAtraso, $configuracionAlertas)
                ) {
                    $clientesVencidosParaNotificar[] = [
                        'cliente' => $cliente,
                        'dias_atraso' => $diasAtraso,
                        'monto' => (float) $factura->monto,
                    ];
                }
            }
        }

        if ($esDiaHabil && !empty($clientesVencidosParaNotificar)) {
            usort($clientesVencidosParaNotificar, fn (array $a, array $b) => $b['dias_atraso'] <=> $a['dias_atraso']);

            $slotNotificacion = now()->format('H:i');
            $notifMasivoCacheKey = "cobranza_notif_vencimiento_masivo:{$today}:{$slotNotificacion}";

            if (!\Illuminate\Support\Facades\Cache::has($notifMasivoCacheKey)) {
                $usuariosANotificar = \App\Models\User::role(['Super Admin', 'Administrador'])->get();
                $usuariosConPermiso = \App\Models\User::permission('cobranza.recibir_alertas')->get();
                $usuariosANotificar = $usuariosANotificar->merge($usuariosConPermiso);
                foreach ($clientesVencidosParaNotificar as $item) {
                    if ($item['cliente']->vendedor) {
                        $usuariosANotificar->push($item['cliente']->vendedor);
                    }
                }
                $usuariosANotificar = $usuariosANotificar->unique('id');

                foreach ($usuariosANotificar as $user) {
                    $user->notify(new \App\Notifications\AlertaCobranzaVencimientoMasivoNotification($clientesVencidosParaNotificar));
                }

                \Illuminate\Support\Facades\Cache::put($notifMasivoCacheKey, true, now()->endOfDay());
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
            $consolidado = (float) ($cliente->facturaCobranzaActiva?->monto ?? 0);

            if ($limiteFinal <= 0 || $consolidado <= 0) {
                continue;
            }

            $alertaPendiente = \App\Models\CobranzaAlerta::where('cliente_id', $cliente->id)
                ->where('tipo', 'limite_superado')
                ->where('estado', 'pendiente')
                ->first();

            if ($consolidado > $limiteFinal) {
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
                        'monto_actual' => $consolidado,
                        'limite' => $limiteFinal,
                    ];
                }
            } elseif ($alertaPendiente) {
                $alertaPendiente->update(['estado' => 'resuelta']);
            }
        }

        if ($esDiaHabil && !empty($alertasLimiteExcedidoMasivo)) {
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
