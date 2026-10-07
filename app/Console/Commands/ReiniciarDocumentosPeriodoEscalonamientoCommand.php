<?php

namespace App\Console\Commands;

use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Services\Escalonamiento\ReiniciarDocumentosPeriodoEscalonamiento;
use App\Services\Escalonamiento\SincronizarReglasPeriodosEscalonamiento;
use Illuminate\Console\Command;

class ReiniciarDocumentosPeriodoEscalonamientoCommand extends Command
{
    protected $signature = 'escalonamiento:reiniciar-documentos-periodo
                            {--anio= : Año del período}
                            {--mes= : Mes del período}
                            {--sincronizar-reglas : Actualiza snapshots desde el catálogo antes del reinicio}';

    protected $description = 'Elimina documentos e importaciones de un período abierto o histórico sin cierre, para volver a cargar el archivo.';

    public function handle(
        ReiniciarDocumentosPeriodoEscalonamiento $reiniciar,
        SincronizarReglasPeriodosEscalonamiento $sincronizar,
    ): int {
        $anio = (int) ($this->option('anio') ?: now()->year);
        $mes = (int) ($this->option('mes') ?: now()->month);

        $periodo = EscalonamientoPeriodo::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        if ($periodo === null) {
            $this->error("No existe período {$anio}-".sprintf('%02d', $mes).'.');

            return self::FAILURE;
        }

        if ($this->option('sincronizar-reglas')) {
            $n = $sincronizar->sincronizarPeriodosEditables();
            $this->info("Snapshots actualizados en {$n} período(s) editables.");
        }

        $conteo = $reiniciar->reiniciar($periodo);
        $this->info('Reinicio completado: '.json_encode($conteo));

        return self::SUCCESS;
    }
}
