<?php

namespace App\Console\Commands;

use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Services\Solicitudes\TipoClienteSolicitudResolver;
use Illuminate\Console\Command;

class BackfillTipoClienteSolicitadoCommand extends Command
{
    protected $signature = 'solicitudes:backfill-tipo-solicitado
                            {--apply : Escribir cambios (por defecto solo muestra el resumen)}';

    protected $description = 'Rellena catalogo_tipo_cliente_id en solicitudes comerciales usando el tipo de la ficha del cliente';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $candidatas = SolicitudTag::query()
            ->whereNull('catalogo_tipo_cliente_id')
            ->whereHas('proceso', fn ($q) => $q->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO))
            ->whereHas('cliente', fn ($q) => $q->whereNotNull('catalogo_tipo_cliente_id'))
            ->with(['cliente.tipo', 'proceso', 'estado'])
            ->orderBy('id')
            ->get();

        if ($candidatas->isEmpty()) {
            $this->info('No hay solicitudes comerciales sin tipo con cliente tipificado.');
            return self::SUCCESS;
        }

        $this->table(
            ['Folio', 'Estado', 'Proceso', 'Tipo a copiar'],
            $candidatas->map(fn (SolicitudTag $s) => [
                'FOL-'.$s->id,
                $s->estado?->nombre,
                $s->proceso?->nombre,
                $s->cliente?->tipo?->nombre,
            ])
        );

        if (!$apply) {
            $this->warn('Modo lectura. Usa --apply para actualizar '.$candidatas->count().' solicitud(es).');
            return self::SUCCESS;
        }

        $actualizadas = 0;
        foreach ($candidatas as $solicitud) {
            $tipo = TipoClienteSolicitudResolver::resolver(null, $solicitud->cliente);
            if (TipoClienteSolicitudResolver::faltaTipoComercial($tipo)) {
                continue;
            }
            $solicitud->update(['catalogo_tipo_cliente_id' => $tipo]);
            $actualizadas++;
        }

        $this->info("Actualizadas: {$actualizadas}");

        return self::SUCCESS;
    }
}
