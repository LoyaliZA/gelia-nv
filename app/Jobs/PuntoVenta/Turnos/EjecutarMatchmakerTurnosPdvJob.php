<?php

namespace App\Jobs\PuntoVenta\Turnos;

use App\Models\Scopes\EsDemoScope;
use App\Models\Sucursal;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EjecutarMatchmakerTurnosPdvJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 30;

    public function __construct(
        public int $sucursalId,
        public string $origenDisparador,
        public ?string $uniqueSuffix = null,
    ) {}

    public function uniqueId(): string
    {
        if ($this->uniqueSuffix !== null && $this->uniqueSuffix !== '') {
            return 'pdv-matchmaker:'.$this->sucursalId.':'.$this->uniqueSuffix;
        }

        return 'pdv-matchmaker:'.$this->sucursalId;
    }

    public function handle(MatchmakerTurnosPdvService $matchmaker): void
    {
        $sucursal = Sucursal::withoutGlobalScope(EsDemoScope::class)->find($this->sucursalId);
        if ($sucursal?->es_demo) {
            return;
        }

        $matchmaker->ejecutar($this->sucursalId, $this->origenDisparador);
    }
}
