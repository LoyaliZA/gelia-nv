<?php

namespace App\Services\Tiendanube\Precios;

final class TiendanubePrecioMotorPoliticaDto
{
    public function __construct(
        public readonly bool $bloquearVentaCero = true,
        public readonly ?string $margenMinimo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function fromArray(array $datos): self
    {
        return new self(
            bloquearVentaCero: array_key_exists('bloquear_venta_cero', $datos)
                ? (bool) $datos['bloquear_venta_cero']
                : true,
            margenMinimo: isset($datos['margen_minimo']) ? (string) $datos['margen_minimo'] : null,
        );
    }

    /**
     * @return array{bloquear_venta_cero: bool, margen_minimo: string|null}
     */
    public function toArray(): array
    {
        return [
            'bloquear_venta_cero' => $this->bloquearVentaCero,
            'margen_minimo' => $this->margenMinimo,
        ];
    }
}
