<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class FisicaConversionModalidad
{
    public function __construct(
        public string $condicion,
        public bool $pendiente,
        public ?string $mensaje = null,
        public bool $entrega_bloqueada = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'condicion' => $this->condicion,
            'pendiente' => $this->pendiente,
            'mensaje' => $this->mensaje,
            'entrega_bloqueada' => $this->entrega_bloqueada,
        ];
    }
}
