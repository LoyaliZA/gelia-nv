<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

use App\Services\Tiendanube\Precios\TiendanubePrecioDecimal;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;

class TiendanubePrecioEjecucionControlRemotoService
{
    /**
     * @param  array<string, mixed>  $producto
     * @return array<string, mixed>|null
     */
    public function varianteDeProducto(array $producto, int $varianteId): ?array
    {
        foreach ($producto['variants'] ?? [] as $variante) {
            if (is_array($variante) && (int) ($variante['id'] ?? 0) === $varianteId) {
                return $variante;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $variante
     * @return array{normal: ?string, promocional: ?string, costo_remoto: ?string}
     */
    public function preciosDeVariante(array $variante): array
    {
        return [
            'normal' => $this->normalizar($variante['price'] ?? null),
            'promocional' => $this->normalizar($variante['promotional_price'] ?? null),
            'costo_remoto' => $this->normalizar($variante['cost'] ?? null),
        ];
    }

    /**
     * @param  array<string, array{intencion: string, valor_final?: mixed}>  $campos
     * @param  array<string, mixed>  $remoto
     * @param  array<string, mixed>  $anteriores
     * @return array{resultado: string, explicacion: string, remoto: array<string, ?string>}
     */
    public function comparar(array $campos, array $remoto, array $anteriores): array
    {
        $coincideObjetivo = true;
        $coincideOrigen = true;

        foreach (TiendanubePrecioDestino::cases() as $destino) {
            $clave = $destino->value;
            $campo = $campos[$clave] ?? [];
            $intencion = TiendanubePrecioIntencion::tryFrom((string) ($campo['intencion'] ?? 'conservar'))
                ?? TiendanubePrecioIntencion::Conservar;
            if ($intencion === TiendanubePrecioIntencion::Conservar) {
                continue;
            }

            $objetivo = $intencion === TiendanubePrecioIntencion::Eliminar
                ? null
                : $this->normalizar($campo['valor_final'] ?? null);
            $valorRemoto = $remoto[$clave] ?? null;
            $origen = $this->normalizar($anteriores[$clave] ?? null);

            if (! $this->iguales($valorRemoto, $objetivo)) {
                $coincideObjetivo = false;
            }
            if (! $this->iguales($valorRemoto, $origen)) {
                $coincideOrigen = false;
            }
        }

        if ($coincideObjetivo) {
            return [
                'resultado' => 'objetivo',
                'explicacion' => 'El valor remoto ya coincide con el aprobado. No se volvió a escribir.',
                'remoto' => $remoto,
            ];
        }

        if ($coincideOrigen) {
            return [
                'resultado' => 'origen',
                'explicacion' => 'El valor remoto coincide con el snapshot de origen.',
                'remoto' => $remoto,
            ];
        }

        return [
            'resultado' => 'conflicto',
            'explicacion' => 'El precio remoto cambió después de la simulación.',
            'remoto' => $remoto,
        ];
    }

    public function iguales(?string $a, ?string $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return TiendanubePrecioDecimal::cmp($a, $b) === 0;
    }

    public function normalizar(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return TiendanubePrecioDecimal::parseImporte(is_string($valor) ? $valor : (string) $valor);
    }
}
