<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use App\Models\HistorialMontoCliente;
use App\Models\CatalogoListaDescuento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AlertaSolicitud;
use App\Notifications\ResumenPagosVencidos; // Importación de la nueva alerta agrupada

class RechazarPagosVencidos extends Command
{
    protected $signature = 'pagos:rechazar-vencidos';
    protected $description = 'Rechaza solicitudes sin pago tras 48 horas y envía un reporte consolidado a las encargadas a las 9:00 AM.';

    public function handle()
    {
        $solicitudesVencidas = SolicitudTag::with('cliente')
            ->where('pago_confirmado', false)
            ->whereIn('catalogo_estado_solicitud_id', [1, 2])
            ->where('created_at', '<', now()->subHours(48))
            ->get();

        if ($solicitudesVencidas->isEmpty()) {
            $this->info("Operación completada: No hay solicitudes vencidas.");
            return;
        }

        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();
        $encargadas = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();
        $contador = 0;

        foreach ($solicitudesVencidas as $solicitud) {
            DB::transaction(function () use ($solicitud, $listas, &$contador) {
                $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
                $cliente = $solicitud->cliente;

                $solicitud->update(['catalogo_estado_solicitud_id' => 4]);

                if ($cliente) {
                    if ($estadoAnteriorId == 2) {
                        $montoAnterior = $cliente->monto_venta_actual;
                        $montoNuevo = max(0, $montoAnterior - $solicitud->monto_cotizado);
                        $nuevaListaId = $this->determinarListaPorMonto($montoNuevo, $listas);

                        HistorialMontoCliente::create([
                            'cliente_id' => $cliente->id,
                            'monto_anterior' => $montoAnterior,
                            'monto_nuevo' => $montoNuevo,
                            'diferencia_aplicada' => -abs($solicitud->monto_cotizado)
                        ]);

                        $cliente->update([
                            'monto_venta_actual' => $montoNuevo,
                            'lista_actual_id' => $nuevaListaId,
                            'catalogo_tipo_cliente_id' => $solicitud->catalogo_tipo_cliente_id ? null : $cliente->catalogo_tipo_cliente_id
                        ]);
                    } else {
                        $cliente->update([
                            'catalogo_tipo_cliente_id' => $solicitud->catalogo_tipo_cliente_id ? null : $cliente->catalogo_tipo_cliente_id
                        ]);
                    }
                }

                AuditoriaSolicitud::create([
                    'solicitud_id' => $solicitud->id,
                    'usuario_id' => 1,
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => 4,
                    'motivo_reporte' => 'SISTEMA AUTOMÁTICO: Plazo de pago expirado (48h).'
                ]);

                // Notificación individual exclusiva para la vendedora del folio
                if ($solicitud->vendedor) {
                    $solicitud->vendedor->notify(new AlertaSolicitud(
                        $solicitud, 
                        'pago_rechazado', 
                        'El tiempo expiró. Solicitud rebotada por falta de pago (48h).'
                    ));
                }

                $contador++;
            });
        }

        // Envío de una sola alerta consolidada por voz para el personal administrativo
        if ($contador > 0 && $encargadas->isNotEmpty()) {
            Notification::send($encargadas, new ResumenPagosVencidos($contador));
        }

        $this->info("Se procesaron {$contador} solicitudes vencidas.");
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if ($lista->nombre === 'COLABORADORES') continue;
            if ($monto >= $lista->monto_requerido) {
                return $lista->id;
            }
        }
        return $listas->where('nombre', 'PUBLICO GENERAL')->first()->id ?? 1; 
    }
}