<?php

namespace App\Services\Tiendanube\Precios;

final class TiendanubePrecioFuenteDto
{
    public function __construct(
        public readonly string $tipo,
        public readonly ?int $listaId,
        public readonly ?int $version,
        public readonly ?string $moneda,
        public readonly ?string $fecha,
        public readonly ?string $valorDecimal,
        public readonly int $productoId,
        public readonly int $varianteId,
        public readonly int $tiendaId,
        public readonly bool $faltante = false,
        public readonly bool $predeterminada = false,
    ) {}

    /**
     * @return array{
     *     tipo: string,
     *     lista_id: int|null,
     *     version: int|null,
     *     moneda: string|null,
     *     fecha: string|null,
     *     valor_decimal: string|null,
     *     producto_id: int,
     *     variante_id: int,
     *     tienda_id: int,
     *     faltante: bool,
     *     predeterminada: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'lista_id' => $this->listaId,
            'version' => $this->version,
            'moneda' => $this->moneda,
            'fecha' => $this->fecha,
            'valor_decimal' => $this->valorDecimal,
            'producto_id' => $this->productoId,
            'variante_id' => $this->varianteId,
            'tienda_id' => $this->tiendaId,
            'faltante' => $this->faltante,
            'predeterminada' => $this->predeterminada,
        ];
    }
}
