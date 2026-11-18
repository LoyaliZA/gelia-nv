<?php

namespace App\Support\ControlPedidos\Conversion;

/**
 * Campos opcionales de POST cerrar-consulta. Null = POST M-01 legado.
 */
readonly class ContextoCierreConsultaEsperado
{
    public function __construct(
        public ?string $modalidad_esperada = null,
        public ?int $version_consulta = null,
    ) {}

    public function tieneModalidad(): bool
    {
        return $this->modalidad_esperada !== null && $this->modalidad_esperada !== '';
    }

    public function tieneVersion(): bool
    {
        return $this->version_consulta !== null;
    }
}
