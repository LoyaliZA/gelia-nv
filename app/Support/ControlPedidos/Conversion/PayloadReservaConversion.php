<?php

namespace App\Support\ControlPedidos\Conversion;

readonly class PayloadReservaConversion
{
    public function __construct(
        public int $usuario_id,
        public string $instancia_id,
        public string $at,
    ) {}

    /** @return array{usuario_id: int, instancia_id: string, at: string} */
    public function toArray(): array
    {
        return [
            'usuario_id' => $this->usuario_id,
            'instancia_id' => $this->instancia_id,
            'at' => $this->at,
        ];
    }

    /** @param  array<string, mixed>|null  $datos */
    public static function fromArray(?array $datos): ?self
    {
        if (! is_array($datos) || ! isset($datos['usuario_id'], $datos['instancia_id'])) {
            return null;
        }

        return new self(
            (int) $datos['usuario_id'],
            (string) $datos['instancia_id'],
            (string) ($datos['at'] ?? ''),
        );
    }
}
