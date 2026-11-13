<?php

namespace App\Console\Commands;

use App\Models\CambioListaImportacionCliente;
use App\Models\Cliente;
use App\Services\Clientes\ClasificacionComercialCliente;
use Illuminate\Console\Command;

class CandidatosAscensoPgCommand extends Command
{
    protected $signature = 'clientes:candidatos-ascenso-pg {--limit=50 : Máximo de filas a listar}';

    protected $description = 'Lista clientes que ascendieron desde Público General en una importación y no tienen una conversión mayorista autorizada.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $listados = 0;
        $total = 0;

        CambioListaImportacionCliente::query()
            ->where('tipo_cambio', CambioListaImportacionCliente::TIPO_ASCENSO)
            ->where('lista_anterior', 'PUBLICO GENERAL')
            ->orderBy('id')
            ->chunkById(200, function ($cambios) use ($limit, &$listados, &$total) {
                $numeros = $cambios->pluck('numero_cliente')->filter()->unique()->all();
                $clientes = Cliente::query()
                    ->whereIn('numero_cliente', $numeros)
                    ->get()
                    ->keyBy('numero_cliente');

                foreach ($cambios as $cambio) {
                    $cliente = $clientes->get($cambio->numero_cliente);
                    if ($cliente && $cliente->modalidad_comercial === ClasificacionComercialCliente::MAYOREO
                        && $cliente->modalidad_origen === ClasificacionComercialCliente::ORIGEN_CONVERSION) {
                        continue;
                    }

                    $total++;
                    if ($listados >= $limit) {
                        continue;
                    }
                    $listados++;
                    $this->line(sprintf(
                        '%s | %s | %s -> %s | monto %s',
                        $cambio->numero_cliente,
                        $cambio->nombre_cliente,
                        $cambio->lista_anterior,
                        $cambio->lista_nueva,
                        $cambio->monto_nuevo,
                    ));
                }
            });

        if ($total === 0) {
            $this->info('Sin candidatos.');

            return self::SUCCESS;
        }

        $this->info("Candidatos: {$total}. No se modificó ningún cliente.");

        return self::SUCCESS;
    }
}
