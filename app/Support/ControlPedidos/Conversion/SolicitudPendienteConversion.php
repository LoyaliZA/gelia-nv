<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class SolicitudPendienteConversion
{
    public function __construct(
        public string $fuente,
        public string $estado,
    ) {}

    /** @return array{fuente: string, estado: string} */
    public function toArray(): array
    {
        return [
            'fuente' => $this->fuente,
            'estado' => $this->estado,
        ];
    }
}
