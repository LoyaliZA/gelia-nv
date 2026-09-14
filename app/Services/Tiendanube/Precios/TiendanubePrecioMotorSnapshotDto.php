<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;

final class TiendanubePrecioMotorSnapshotDto
{
    /**
     * @param  list<TiendanubePrecioFuenteDto>  $fuentes
     */
    public function __construct(
        public readonly int $tiendaId,
        public readonly int $productoId,
        public readonly int $varianteId,
        public readonly array $fuentes,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function fromArray(array $datos): self
    {
        $fuentes = [];
        foreach ($datos['fuentes'] ?? [] as $fuente) {
            $fuentes[] = $fuente instanceof TiendanubePrecioFuenteDto
                ? $fuente
                : new TiendanubePrecioFuenteDto(
                    tipo: (string) ($fuente['tipo'] ?? ''),
                    listaId: isset($fuente['lista_id']) ? (int) $fuente['lista_id'] : null,
                    version: isset($fuente['version']) ? (int) $fuente['version'] : null,
                    moneda: $fuente['moneda'] ?? null,
                    fecha: $fuente['fecha'] ?? null,
                    valorDecimal: $fuente['valor_decimal'] ?? null,
                    productoId: (int) ($fuente['producto_id'] ?? $datos['producto_id'] ?? 0),
                    varianteId: (int) ($fuente['variante_id'] ?? $datos['variante_id'] ?? 0),
                    tiendaId: (int) ($fuente['tienda_id'] ?? $datos['tienda_id'] ?? 0),
                    faltante: (bool) ($fuente['faltante'] ?? false),
                    predeterminada: (bool) ($fuente['predeterminada'] ?? false),
                );
        }

        return new self(
            tiendaId: (int) ($datos['tienda_id'] ?? 0),
            productoId: (int) ($datos['producto_id'] ?? 0),
            varianteId: (int) ($datos['variante_id'] ?? 0),
            fuentes: $fuentes,
        );
    }

    public function valor(TiendanubePrecioCampoCondicion $campo, ?int $listaId = null): ?string
    {
        foreach ($this->fuentes as $fuente) {
            if ($fuente->tipo !== $campo->value) {
                continue;
            }
            if ($campo === TiendanubePrecioCampoCondicion::ListaReferencia && $fuente->listaId !== $listaId) {
                continue;
            }
            if ($fuente->faltante) {
                return null;
            }

            return $fuente->valorDecimal;
        }

        return null;
    }

    public function tieneValor(TiendanubePrecioCampoCondicion $campo, ?int $listaId = null): bool
    {
        return $this->valor($campo, $listaId) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tienda_id' => $this->tiendaId,
            'producto_id' => $this->productoId,
            'variante_id' => $this->varianteId,
            'fuentes' => array_map(
                fn (TiendanubePrecioFuenteDto $f) => $f->toArray(),
                $this->fuentes
            ),
        ];
    }
}
