<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;
use App\Support\Tiendanube\Precios\TiendanubePrecioCondicionOperador;

final class TiendanubePrecioMotorCondicionDto
{
    public function __construct(
        public readonly TiendanubePrecioCampoCondicion $campo,
        public readonly TiendanubePrecioCondicionOperador $operador,
        public readonly ?string $valor = null,
        public readonly ?string $valorHasta = null,
        public readonly bool $inclusivoDesde = true,
        public readonly bool $inclusivoHasta = true,
        public readonly ?int $listaId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function fromArray(array $datos): self
    {
        $operador = TiendanubePrecioCondicionOperador::from((string) ($datos['operador'] ?? ''));

        return new self(
            campo: TiendanubePrecioCampoCondicion::from((string) ($datos['campo'] ?? '')),
            operador: $operador,
            valor: isset($datos['valor']) ? (string) $datos['valor'] : null,
            valorHasta: isset($datos['valor_hasta']) ? (string) $datos['valor_hasta'] : null,
            inclusivoDesde: array_key_exists('inclusivo_desde', $datos) ? (bool) $datos['inclusivo_desde'] : true,
            inclusivoHasta: array_key_exists('inclusivo_hasta', $datos) ? (bool) $datos['inclusivo_hasta'] : true,
            listaId: isset($datos['lista_id']) ? (int) $datos['lista_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'campo' => $this->campo->value,
            'operador' => $this->operador->value,
            'valor' => $this->valor,
            'valor_hasta' => $this->valorHasta,
            'inclusivo_desde' => $this->inclusivoDesde,
            'inclusivo_hasta' => $this->inclusivoHasta,
            'lista_id' => $this->listaId,
        ];
    }
}
