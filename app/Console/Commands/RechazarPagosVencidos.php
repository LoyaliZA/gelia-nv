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

class RechazarPagosVencidos extends Command
{
    protected $signature = 'pagos:rechazar-vencidos';
    protected $description = 'Rechaza automáticamente las solicitudes con pago pendiente tras 24 horas y revierte beneficios.';

    public function handle()
    {
        // Optimizamos la consulta con 'with' para evitar el problema N+1 al traer los clientes
        $solicitudesVencidas = SolicitudTag::with('cliente')
            ->where('pago_confirmado', false)
            ->where('catalogo_estado_solicitud_id', 1) // 1 = Pendiente
            ->where('created_at', '<', now()->subHours(24))
            ->get();

        if ($solicitudesVencidas->isEmpty()) {
            $this->info("Operación completada: No hay solicitudes vencidas en este momento.");
            return;
        }

        // Cargamos catálogos en memoria una sola vez para no golpear la BD en el bucle
        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();
        $encargadas = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();

        $contador = 0;

        foreach ($solicitudesVencidas as $solicitud) {
            DB::transaction(function () use ($solicitud, $listas, $encargadas, &$contador) {
                
                $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
                $cliente = $solicitud->cliente;

                // 1. Marcar la solicitud como Incorrecta (ID 4)
                $solicitud->update(['catalogo_estado_solicitud_id' => 4]);

                // 2. Lógica de reversión al Cliente
                if ($cliente) {
                    $montoAnterior = $cliente->monto_venta_actual;
                    
                    // Restamos el monto, previniendo saldos negativos
                    $montoNuevo = max(0, $montoAnterior - $solicitud->monto_cotizado);
                    
                    // Recalculamos la lista a la que pertenece con su monto degradado
                    $nuevaListaId = $this->determinarListaPorMonto($montoNuevo, $listas);

                    // Dejamos huella en el historial de montos
                    HistorialMontoCliente::create([
                        'cliente_id' => $cliente->id,
                        'monto_anterior' => $montoAnterior,
                        'monto_nuevo' => $montoNuevo,
                        'diferencia_aplicada' => -abs($solicitud->monto_cotizado)
                    ]);

                    // Actualizamos al cliente quitando etiquetas y degradando lista
                    $cliente->update([
                        'monto_venta_actual' => $montoNuevo,
                        'lista_actual_id' => $nuevaListaId,
                        'catalogo_tipo_cliente_id' => $solicitud->catalogo_tipo_cliente_id ? null : $cliente->catalogo_tipo_cliente_id
                    ]);
                }

                // 3. Registrar Auditoría
                AuditoriaSolicitud::create([
                    'solicitud_id' => $solicitud->id,
                    'usuario_id' => 1, // 1 = El Sistema
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => 4,
                    'motivo_reporte' => 'SISTEMA AUTOMÁTICO: Tiempo de pago expirado (24h). Se descontó el monto y se revirtieron los beneficios del cliente.'
                ]);

                // 4. Notificar a las Encargadas (Aviso para Wizerp)
                if ($encargadas->isNotEmpty()) {
                    Notification::send($encargadas, new AlertaSolicitud(
                        $solicitud, 
                        'pago_rechazado', 
                        "SISTEMA: La solicitud FOL-{$solicitud->id} venció (24h). Ajusta a este cliente en WIZER a su lista anterior."
                    ));
                }

                // 5. Notificar a la Vendedora
                if ($solicitud->vendedor) {
                    $solicitud->vendedor->notify(new AlertaSolicitud(
                        $solicitud, 
                        'pago_rechazado', 
                        'El tiempo expiró. Solicitud rebotada por falta de pago (24h). El cliente regresó a su nivel previo.'
                    ));
                }

                $contador++;
            });
        }

        $this->info("Se rechazaron {$contador} solicitudes vencidas y se ajustaron los clientes.");
    }

    /**
     * Evalúa el monto actual y retorna el ID de la lista que le corresponde.
     */
    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if ($lista->nombre === 'COLABORADORES') continue;
            if ($monto >= $lista->monto_requerido) {
                return $lista->id;
            }
        }
        // Si no califica a nada, regresa a Público General
        return $listas->where('nombre', 'PUBLICO GENERAL')->first()->id ?? 1; 
    }
}