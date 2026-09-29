<?php

namespace App\Jobs\Medios;

use App\Models\Medios\Medio;
use App\Services\Medios\MaterializarMedioLocalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MaterializarMedioLocalJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(
        public int $medioId,
    ) {}

    public function handle(MaterializarMedioLocalService $servicio): void
    {
        $medio = Medio::query()->find($this->medioId);
        if (! $medio instanceof Medio) {
            return;
        }

        $servicio->ejecutar($medio);
    }
}
