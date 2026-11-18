<?php

namespace App\Jobs\PuntoVenta\Turnos;

use App\Models\Scopes\EsDemoScope;
use App\Models\Sucursal;
use App\Services\PuntoVenta\Turnos\MatchmakerTurnosPdvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class EjecutarMatchmakerTurnosPdvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El solapamiento reencola (releaseAfter) y cuenta como intento.
     * Las excepciones reales se cortan antes con maxExceptions.
     */
    public int $tries = 50;

    public int $maxExceptions = 3;

    public function __construct(
        public int $sucursalId,
        public string $origenDisparador,
        public ?string $uniqueSuffix = null,
    ) {}

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('pdv-matchmaker:'.$this->sucursalId))
                ->releaseAfter(1)
                ->expireAfter(60),
        ];
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
