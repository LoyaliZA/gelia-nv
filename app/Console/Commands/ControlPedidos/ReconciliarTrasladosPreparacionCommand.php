<?php

namespace App\Console\Commands\ControlPedidos;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTraspaso;
use App\Services\ControlPedidos\SincronizarTareaDesdeTraspasoService;
use Illuminate\Console\Command;

class ReconciliarTrasladosPreparacionCommand extends Command
{
    protected $signature = 'control-pedidos:reconciliar-traslados-preparacion';

    protected $description = 'Sincroniza tareas de preparación cuyo traspaso CEDIS ya está verificado/incorrecto pero la tarea no se actualizó.';

    public function handle(SincronizarTareaDesdeTraspasoService $sync): int
    {
        $idVerificada = CatalogoEstadoSolicitud::idDe('Verificada');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');

        $query = SolicitudTraspaso::query()
            ->whereNotNull('tarea_preparacion_id')
            ->with(['tareaPreparacion', 'estado']);

        if ($idVerificada || $idIncorrecta) {
            $query->where(function ($q) use ($idVerificada, $idIncorrecta) {
                if ($idVerificada) {
                    $q->orWhere('catalogo_estado_solicitud_id', $idVerificada);
                }
                if ($idIncorrecta) {
                    $q->orWhere('catalogo_estado_solicitud_id', $idIncorrecta);
                }
            });
        }

        $corregidos = 0;
        foreach ($query->cursor() as $solicitud) {
            $antes = $solicitud->tareaPreparacion?->estado;
            $tarea = $sync->reconciliar($solicitud);
            if ($tarea && $antes !== $tarea->estado) {
                $corregidos++;
                $this->line("Tarea {$tarea->id}: {$antes} → {$tarea->estado} (traspaso {$solicitud->folio})");
            }
        }

        $this->info("Reconciliación terminada. Tareas actualizadas: {$corregidos}");

        return self::SUCCESS;
    }
}
