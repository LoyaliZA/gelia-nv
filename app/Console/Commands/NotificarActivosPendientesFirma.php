<?php

namespace App\Console\Commands;

use App\Models\ActivoAsignacion;
use App\Models\User;
use App\Notifications\AlertaActivo;
use Illuminate\Console\Command;

class NotificarActivosPendientesFirma extends Command
{
    protected $signature = 'activos:notificar-pendientes-firma';

    protected $description = 'Envía notificaciones a colaboradores que tienen activos asignados pendientes de firmar de recibido';

    public function handle(): int
    {
        $asignaciones = ActivoAsignacion::with(['activo.tipo', 'activo.departamento', 'activo.responsable', 'usuario'])
            ->where('activa', true)
            ->where('firmado', false)
            ->get();

        if ($asignaciones->isEmpty()) {
            $this->info('No hay activos pendientes de firmar.');
            return self::SUCCESS;
        }

        $enviados = 0;

        foreach ($asignaciones as $asignacion) {
            $colaborador = $asignacion->usuario;
            $activo = $asignacion->activo;

            if (!$colaborador || !$activo) {
                continue;
            }

            // Enviar notificación al colaborador
            $colaborador->notify(new AlertaActivo(
                $activo,
                'activo_pendiente_firma',
                'Tienes un activo asignado pendiente de firmar de recibido'
            ));

            $enviados++;
        }

        $this->info("Notificaciones de firma enviadas: {$enviados}.");

        return self::SUCCESS;
    }
}
