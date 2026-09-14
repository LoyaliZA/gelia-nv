<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;

final class TiendanubePrecioMotorResultadoCampoDto
{
    /**
     * @param  list<string>  $alertas
     * @param  list<string>  $errores
     */
    public function __construct(
        public readonly TiendanubePrecioDestino $destino,
        public readonly TiendanubePrecioIntencion $intencion,
        public readonly ?string $valorBruto = null,
        public readonly ?string $valorFinal = null,
        public readonly ?string $reglaId = null,
        public readonly string $explicacion = '',
        public readonly array $alertas = [],
        public readonly array $errores = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'destino' => $this->destino->value,
            'intencion' => $this->intencion->value,
            'valor_bruto' => $this->valorBruto,
            'valor_final' => $this->valorFinal,
            'regla_id' => $this->reglaId,
            'explicacion' => $this->explicacion,
            'alertas' => $this->alertas,
            'errores' => $this->errores,
        ];
    }
}
