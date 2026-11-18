<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class ResultadoPoliticaConversion
{
    public function __construct(
        public bool $permitido,
        public ?string $motivo_bloqueo = null,
    ) {}
}
