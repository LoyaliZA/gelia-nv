<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Escalonamiento\EscalonamientoAutoridad;
use Illuminate\Console\Command;

class ValidarCorteAutoridadEscalonamientoCommand extends Command
{
    protected $signature = 'escalonamiento:validar-corte-autoridad {--limit=50 : Máximo de filas a listar}';

    protected $description = 'Lista clientes cuyo monto o lista operativa no coinciden con el período oficial del módulo.';

    public function handle(EscalonamientoAutoridad $autoridad): int
    {
        if (! $autoridad->estaActiva()) {
            $this->warn('ESCALONAMIENTO_AUTORIDAD_ACTIVA no está activa. No hay corte que validar.');

            return self::SUCCESS;
        }

        $periodo = $autoridad->periodoOperativo();
        if (! $periodo) {
            $this->error('No hay período abierto para validar.');

            return self::FAILURE;
        }

        $this->info("Período oficial: {$periodo->anio}-".str_pad((string) $periodo->mes, 2, '0', STR_PAD_LEFT)." (ID {$periodo->id})");

        $limit = max(1, (int) $this->option('limit'));
        $divergencias = 0;
        $listadas = 0;

        EscalonamientoResumenCliente::query()
            ->with('cliente:id,numero_cliente,nombre,monto_venta_actual,lista_actual_id')
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->orderBy('cliente_id')
            ->chunkById(200, function ($resumenes) use ($autoridad, $periodo, $limit, &$divergencias, &$listadas) {
                foreach ($resumenes as $resumen) {
                    $cliente = $resumen->cliente;
                    if (! $cliente || ! $autoridad->clienteGobernadoPorModulo($cliente, $periodo)) {
                        continue;
                    }

                    $montoCliente = bcadd((string) $cliente->monto_venta_actual, '0', 2);
                    $montoModulo = bcadd((string) $resumen->acumulado, '0', 2);
                    $listaOperativa = (int) ($cliente->lista_actual_id ?? 0);
                    $listaVigente = (int) ($resumen->lista_vigente_id ?? 0);

                    $montoOk = bccomp($montoCliente, $montoModulo, 2) === 0;
                    $listaOk = $listaVigente === 0 || $listaOperativa === $listaVigente;

                    if ($montoOk && $listaOk) {
                        continue;
                    }

                    $divergencias++;
                    if ($listadas >= $limit) {
                        continue;
                    }

                    $listadas++;
                    $this->line(sprintf(
                        'Cliente %s (%s): monto cliente=%s módulo=%s | lista operativa=%d vigente=%d',
                        $cliente->numero_cliente,
                        $cliente->nombre,
                        $montoCliente,
                        $montoModulo,
                        $listaOperativa,
                        $listaVigente,
                    ));
                }
            });

        if ($divergencias === 0) {
            $this->info('Sin divergencias entre cliente y resumen del módulo.');

            return self::SUCCESS;
        }

        $this->warn("Total con divergencia: {$divergencias}".($divergencias > $listadas ? " (mostrando {$listadas})" : ''));

        return self::FAILURE;
    }
}
