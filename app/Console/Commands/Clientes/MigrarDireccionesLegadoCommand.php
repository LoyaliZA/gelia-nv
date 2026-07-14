<?php

namespace App\Console\Commands\Clientes;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Services\Clientes\Direcciones\NormalizadorDireccion;
use Illuminate\Console\Command;

class MigrarDireccionesLegadoCommand extends Command
{
    protected $signature = 'direcciones:migrar-legado
                            {--dry-run : Simula sin escribir}
                            {--lote=200 : Tamaño de lote}';

    protected $description = 'Backfill idempotente de cliente_direcciones desde campos *_contacto';

    public function handle(
        GestionDireccionesClienteService $gestion,
        NormalizadorDireccion $normalizador,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $lote = max(1, (int) $this->option('lote'));

        $migradas = 0;
        $omitidas = 0;
        $ambiguas = 0;
        $duplicadas = 0;
        $fallidas = 0;

        Cliente::query()
            ->orderBy('id')
            ->chunkById($lote, function ($clientes) use (
                $gestion,
                $normalizador,
                $dryRun,
                &$migradas,
                &$omitidas,
                &$ambiguas,
                &$duplicadas,
                &$fallidas,
            ) {
                foreach ($clientes as $cliente) {
                    try {
                        $resultado = $this->procesarCliente($cliente, $gestion, $normalizador, $dryRun);
                        match ($resultado) {
                            'migrada' => $migradas++,
                            'omitida' => $omitidas++,
                            'ambigua' => $ambiguas++,
                            'duplicada' => $duplicadas++,
                            default => $fallidas++,
                        };
                    } catch (\Throwable $e) {
                        $fallidas++;
                        $this->error("Cliente {$cliente->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->table(['Resultado', 'Cantidad'], [
            ['Migradas', $migradas],
            ['Omitidas', $omitidas],
            ['Ambiguas', $ambiguas],
            ['Duplicadas (ya existían)', $duplicadas],
            ['Fallidas', $fallidas],
            ['Modo', $dryRun ? 'dry-run' : 'escritura'],
        ]);

        return self::SUCCESS;
    }

    private function procesarCliente(
        Cliente $cliente,
        GestionDireccionesClienteService $gestion,
        NormalizadorDireccion $normalizador,
        bool $dryRun,
    ): string {
        $yaTiene = ClienteDireccion::query()
            ->where('cliente_id', $cliente->id)
            ->where('origen', ClienteDireccion::ORIGEN_LEGACY)
            ->exists();

        if ($yaTiene) {
            return 'duplicada';
        }

        $tieneActiva = ClienteDireccion::query()
            ->where('cliente_id', $cliente->id)
            ->where('esta_activa', true)
            ->exists();

        if ($tieneActiva) {
            return 'omitida';
        }

        $direccion = trim((string) $cliente->direccion_contacto);
        $colonia = trim((string) $cliente->colonia_contacto);
        $cp = trim((string) $cliente->cp_contacto);

        if ($direccion === '' && $colonia === '' && $cp === '') {
            return 'omitida';
        }

        $ambigua = $direccion !== '' && $colonia === '' && str_contains($direccion, ',');

        $datos = $normalizador->ejecutar([
            'nombre_destinatario' => filled($cliente->nombre) ? $cliente->nombre : 'Sin nombre',
            'telefono_destinatario' => $cliente->telefono,
            'calle' => $direccion !== '' ? $direccion : null,
            'colonia' => $colonia !== '' ? $colonia : null,
            'municipio' => $cliente->municipio_contacto,
            'ciudad' => $cliente->municipio_contacto,
            'estado' => $cliente->estado_contacto,
            'pais' => $cliente->pais_contacto,
            'codigo_postal' => $cp !== '' ? $cp : null,
            'etiqueta' => 'Dirección principal (legado)',
            'tipo_direccion' => ClienteDireccion::TIPO_ENVIO,
            'estado_verificacion' => $ambigua
                ? ClienteDireccion::ESTADO_PENDING
                : ClienteDireccion::ESTADO_PENDING,
        ]);

        if ($dryRun) {
            return $ambigua ? 'ambigua' : 'migrada';
        }

        $gestion->crearPrimeraDireccion($cliente->id, $datos, [
            'origen' => ClienteDireccion::ORIGEN_LEGACY,
            'verificar' => false,
        ]);

        return $ambigua ? 'ambigua' : 'migrada';
    }
}
