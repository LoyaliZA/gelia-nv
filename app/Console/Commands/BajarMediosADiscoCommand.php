<?php

namespace App\Console\Commands;

use App\Models\Medios\Medio;
use App\Services\Medios\MaterializarMedioLocalService;
use Illuminate\Console\Command;

class BajarMediosADiscoCommand extends Command
{
    protected $signature = 'medios:bajar-a-disco';

    protected $description = 'Copia al disco local los medios de publicidad que siguen solo en R2';

    public function handle(MaterializarMedioLocalService $servicio): int
    {
        $medios = Medio::query()
            ->where('proposito', Medio::PROPOSITO_PDV_PUBLICIDAD)
            ->where('estado', Medio::ESTADO_READY)
            ->whereNull('ruta_local')
            ->whereNotNull('object_key')
            ->orderBy('id')
            ->get();

        $copiados = 0;
        $errores = 0;
        foreach ($medios as $medio) {
            try {
                $servicio->ejecutar($medio);
                $medio->refresh();
                if ($medio->estado === Medio::ESTADO_READY && filled($medio->ruta_local)) {
                    $copiados++;
                } else {
                    $errores++;
                    $this->error('Medio '.$medio->id.' no quedó en disco.');
                }
            } catch (\Throwable $e) {
                $errores++;
                $this->error('Medio '.$medio->id.': '.$e->getMessage());
            }
        }

        $this->info('Copiados: '.$copiados.'. Con error: '.$errores.'.');

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
