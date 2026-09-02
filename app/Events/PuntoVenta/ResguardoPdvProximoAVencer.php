<?php

namespace App\Events\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdv;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResguardoPdvProximoAVencer implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array{
     *   clasificaciones: array<string, bool>,
     *   fecha_limite_custodia: string|null,
     *   fecha_limite_rezago: string|null,
     *   plazos_snapshot: array<string, mixed>|null
     * }  $evaluacion
     */
    public function __construct(
        public ResguardoPdv $resguardo,
        public int $sucursalId,
        public string $idempotencyKey,
        public array $evaluacion,
    ) {}
}
