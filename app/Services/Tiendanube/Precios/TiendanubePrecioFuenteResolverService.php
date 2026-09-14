<?php

namespace App\Services\Tiendanube\Precios;

use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use Illuminate\Support\Collection;

class TiendanubePrecioFuenteResolverService
{
    /**
     * @param  list<int>  $varianteIds
     * @param  list<string>|null  $tipos
     * @return list<array{
     *     variante_id: int,
     *     producto_id: int|null,
     *     existe: bool,
     *     fuentes: list<array<string, mixed>>,
     *     faltantes: list<string>
     * }>
     */
    public function resolver(int $storeId, array $varianteIds, ?array $tipos = null): array
    {
        $varianteIds = array_values(array_unique(array_map('intval', $varianteIds)));
        $tipos = $tipos === null || $tipos === []
            ? $this->tiposPorDefecto()
            : array_values(array_unique($tipos));

        $variantes = TiendanubeProductoVariante::query()
            ->whereIn('id', $varianteIds)
            ->get()
            ->keyBy('id');

        $listasActivas = TiendanubePrecioLista::query()
            ->where('store_id', $storeId)
            ->activas()
            ->get();

        $locales = TiendanubePrecioFuenteVersion::query()
            ->where('store_id', $storeId)
            ->whereIn('variante_id', $varianteIds)
            ->whereIn('tipo', [
                TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
                TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
            ])
            ->orderByDesc('version')
            ->get()
            ->groupBy(fn (TiendanubePrecioFuenteVersion $v) => $v->variante_id.':'.$v->fuente_clave)
            ->map(fn ($group) => $group->first());

        $salida = [];
        foreach ($varianteIds as $varianteId) {
            $variante = $variantes->get($varianteId);
            $productoId = $variante ? (int) $variante->producto_id : 0;
            $fuentes = [];
            $faltantes = [];

            foreach ($tipos as $tipo) {
                if ($tipo === TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA) {
                    foreach ($listasActivas as $lista) {
                        $dto = $this->fuenteLocal(
                            $locales,
                            $storeId,
                            $varianteId,
                            $productoId,
                            TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
                            (int) $lista->id
                        );
                        $fuentes[] = $dto->toArray();
                        if ($dto->faltante) {
                            $faltantes[] = TiendanubePrecioFuenteVersion::claveFuente(
                                TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
                                (int) $lista->id
                            );
                        }
                    }

                    continue;
                }

                if (in_array($tipo, [
                    TiendanubePrecioFuenteVersion::TIPO_COSTO_REMOTO_ACTUAL,
                    TiendanubePrecioFuenteVersion::TIPO_PRECIO_NORMAL_ACTUAL,
                    TiendanubePrecioFuenteVersion::TIPO_PRECIO_PROMOCIONAL_ACTUAL,
                ], true)) {
                    $dto = $this->fuenteRemota($tipo, $storeId, $varianteId, $productoId, $variante);
                    $fuentes[] = $dto->toArray();
                    if ($dto->faltante) {
                        $faltantes[] = $tipo;
                    }

                    continue;
                }

                $dto = $this->fuenteLocal(
                    $locales,
                    $storeId,
                    $varianteId,
                    $productoId,
                    TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
                    null
                );
                $fuentes[] = $dto->toArray();
                if ($dto->faltante) {
                    $faltantes[] = TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL;
                }
            }

            $salida[] = [
                'variante_id' => $varianteId,
                'producto_id' => $variante ? $productoId : null,
                'existe' => $variante !== null,
                'fuentes' => $fuentes,
                'faltantes' => $faltantes,
            ];
        }

        return $salida;
    }

    /**
     * @return list<string>
     */
    public function tiposPorDefecto(): array
    {
        return [
            TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
            TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
            TiendanubePrecioFuenteVersion::TIPO_COSTO_REMOTO_ACTUAL,
            TiendanubePrecioFuenteVersion::TIPO_PRECIO_NORMAL_ACTUAL,
            TiendanubePrecioFuenteVersion::TIPO_PRECIO_PROMOCIONAL_ACTUAL,
        ];
    }

    /**
     * @param  Collection<string, TiendanubePrecioFuenteVersion>  $locales
     */
    private function fuenteLocal(
        $locales,
        int $storeId,
        int $varianteId,
        int $productoId,
        string $tipo,
        ?int $listaId,
    ): TiendanubePrecioFuenteDto {
        $clave = TiendanubePrecioFuenteVersion::claveFuente($tipo, $listaId);
        $row = $locales->get($varianteId.':'.$clave);
        $hayCostoLocal = $tipo === TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL && $row !== null;

        if (! $row) {
            return new TiendanubePrecioFuenteDto(
                tipo: $tipo,
                listaId: $listaId,
                version: null,
                moneda: null,
                fecha: null,
                valorDecimal: null,
                productoId: $productoId,
                varianteId: $varianteId,
                tiendaId: $storeId,
                faltante: true,
                predeterminada: false,
            );
        }

        return new TiendanubePrecioFuenteDto(
            tipo: $tipo,
            listaId: $listaId,
            version: (int) $row->version,
            moneda: (string) $row->moneda,
            fecha: $row->fecha?->toIso8601String(),
            valorDecimal: $row->valorDecimalString(),
            productoId: (int) $row->producto_id,
            varianteId: (int) $row->variante_id,
            tiendaId: $storeId,
            faltante: false,
            predeterminada: $hayCostoLocal,
        );
    }

    private function fuenteRemota(
        string $tipo,
        int $storeId,
        int $varianteId,
        int $productoId,
        ?TiendanubeProductoVariante $variante,
    ): TiendanubePrecioFuenteDto {
        $columna = match ($tipo) {
            TiendanubePrecioFuenteVersion::TIPO_COSTO_REMOTO_ACTUAL => 'cost',
            TiendanubePrecioFuenteVersion::TIPO_PRECIO_NORMAL_ACTUAL => 'price',
            default => 'promotional_price',
        };

        $raw = $variante?->getRawOriginal($columna);
        $valor = TiendanubePrecioImporte::formatStored($raw);
        $faltante = $valor === null;

        return new TiendanubePrecioFuenteDto(
            tipo: $tipo,
            listaId: null,
            version: null,
            moneda: $faltante ? null : 'MXN',
            fecha: $variante?->updated_at?->toIso8601String(),
            valorDecimal: $valor,
            productoId: $productoId,
            varianteId: $varianteId,
            tiendaId: $storeId,
            faltante: $faltante,
            predeterminada: false,
        );
    }
}
