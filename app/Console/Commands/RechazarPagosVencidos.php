<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolicitudTag;
use App\Models\Cliente;
use App\Models\AuditoriaSolicitud;
use Illuminate\Support\Facades\DB;

class RechazarPagosVencidos extends Command
{
    protected $signature = 'pagos:rechazar-vencidos';
    protected $description = 'Rechaza automáticamente las solicitudes con pago pendiente tras 24 horas.';

    public function handle()
    {
        // Buscamos solicitudes pendientes, sin pago confirmado, creadas hace más de 24 horas
        $solicitudesVencidas = SolicitudTag::where('pago_confirmado', false)
            ->where('catalogo_estado_solicitud_id', 1) // 1 = Pendiente
            ->where('created_at', '<', now()->subHours(24))
            ->get();

        $contador = 0;

        foreach ($solicitudesVencidas as $solicitud) {
            DB::transaction(function () use ($solicitud, &$contador) {
                // 1. Marcar como Incorrecta (ID 4)
                $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
                $solicitud->update(['catalogo_estado_solicitud_id' => 4]);

                // 2. Revertir Tipo de Cliente si se había asignado en esta solicitud
                if ($solicitud->catalogo_tipo_cliente_id && $solicitud->cliente_id) {
                    Cliente::where('id', $solicitud->cliente_id)->update(['catalogo_tipo_cliente_id' => null]);
                }

                // 3. Registrar Auditoría
                AuditoriaSolicitud::create([
                    'solicitud_id' => $solicitud->id,
                    'usuario_id' => 1, // ID del Sistema/SuperAdmin
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => 4,
                    'motivo_reporte' => 'SISTEMA AUTOMÁTICO: Pago no confirmado en 24 hrs. Se revirtieron cambios.'
                ]);

                // 4. Notificar a la vendedora (Usa la notificación de la clase pasada)
                if ($solicitud->vendedor) {
                    $solicitud->vendedor->notify(new \App\Notifications\AlertaSolicitud(
                        $solicitud, 
                        'pago_rechazado', 
                        'El tiempo expiró. Solicitud rebotada por falta de pago (24h).'
                    ));
                }

                $contador++;
            });
        }

        $this->info("Se rechazaron {$contador} solicitudes vencidas automáticamente.");
    }
}