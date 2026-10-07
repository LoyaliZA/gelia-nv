<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Escalonamiento\EvaluarListaClienteEscalonamiento;
use Illuminate\Console\Command;

class BackfillEscalonamientoResumenesListasCommand extends Command
{
    protected $signature = 'escalonamiento:backfill-resumenes-listas';

    protected $description = 'Recalcula lista_base y lista_vigente en resúmenes de escalonamiento existentes.';

    public function handle(EvaluarListaClienteEscalonamiento $evaluar): int
    {
        $actualizados = 0;

        EscalonamientoResumenCliente::query()
            ->with(['periodo.reglaVersion', 'cliente'])
            ->orderBy('id')
            ->chunkById(100, function ($resumenes) use ($evaluar, &$actualizados) {
                foreach ($resumenes as $resumen) {
                    $periodo = $resumen->periodo;
                    $cliente = $resumen->cliente;
                    if (! $periodo || ! $cliente) {
                        continue;
                    }

                    $evaluar->sincronizarResumen(
                        $periodo,
                        $cliente,
                        $resumen,
                        bcadd((string) $resumen->acumulado, '0', 2),
                    );
                    $resumen->save();
                    $actualizados++;
                }
            });

        $this->info("Resúmenes actualizados: {$actualizados}");

        return self::SUCCESS;
    }
}
