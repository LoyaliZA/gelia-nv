<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Escalonamiento\EvaluarListaClienteEscalonamiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconstruirResumenesEscalonamientoCommand extends Command
{
    protected $signature = 'escalonamiento:reconstruir-resumenes {periodo_id : ID del período}';

    protected $description = 'Recalcula acumulados de resúmenes por cliente desde los movimientos del período.';

    public function handle(EvaluarListaClienteEscalonamiento $evaluarLista): int
    {
        $periodo = EscalonamientoPeriodo::query()->find($this->argument('periodo_id'));
        if (! $periodo) {
            $this->error('Período no encontrado.');

            return self::FAILURE;
        }

        if (! $periodo->permiteBackfillDocumentos() && $periodo->estado !== EscalonamientoPeriodo::ESTADO_CERRADO) {
            $this->error('El período no admite reconstrucción en su estado actual.');

            return self::FAILURE;
        }

        $totales = EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->selectRaw('cliente_id, sum(efecto) as acumulado')
            ->groupBy('cliente_id')
            ->pluck('acumulado', 'cliente_id');

        DB::transaction(function () use ($periodo, $totales, $evaluarLista) {
            foreach ($totales as $clienteId => $acumulado) {
                $acumulado = bcadd((string) $acumulado, '0', 2);
                $cliente = Cliente::query()->findOrFail($clienteId);
                $resumen = EscalonamientoResumenCliente::query()
                    ->where('escalonamiento_periodo_id', $periodo->id)
                    ->where('cliente_id', $clienteId)
                    ->first();

                if ($resumen) {
                    $evaluarLista->sincronizarResumen($periodo, $cliente, $resumen, $acumulado);
                    $resumen->save();
                } else {
                    $evaluarLista->crearResumen($periodo, $cliente, $acumulado);
                }
            }
        });

        $this->info('Resúmenes reconstruidos para '.$periodo->etiquetaMes().' ('.$totales->count().' clientes).');

        return self::SUCCESS;
    }
}
