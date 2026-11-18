<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class DestinoConversionModalidad
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public bool $habilitado,
        public ?string $motivo_bloqueo = null,
    ) {}

    /** @return array{codigo: string, nombre: string, habilitado: bool, motivo_bloqueo: ?string} */
    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'habilitado' => $this->habilitado,
            'motivo_bloqueo' => $this->motivo_bloqueo,
        ];
    }
}
