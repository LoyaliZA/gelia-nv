<?php

namespace App\Services\Tiendanube\Gobernador;

final class TiendanubeTurnoResultado
{
    public function __construct(
        public readonly bool $concedido,
        public readonly string $motivo,
        public readonly int $reanudarDespuesAt,
        public readonly string $fase,
        public readonly string $operacionId,
        public readonly string $clase,
    ) {}
}
