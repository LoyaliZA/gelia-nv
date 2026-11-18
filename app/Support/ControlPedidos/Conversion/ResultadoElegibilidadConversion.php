<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class ResultadoElegibilidadConversion
{
    public function __construct(
        public bool $permitido,
        public bool $consulta_ya_cerrada_alguna_vez,
        public ?string $motivo_bloqueo = null,
        public ?string $codigo = null,
    ) {}
}
