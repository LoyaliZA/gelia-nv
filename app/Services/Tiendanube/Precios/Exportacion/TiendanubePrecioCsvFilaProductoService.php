<?php

namespace App\Services\Tiendanube\Precios\Exportacion;

use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Services\Tiendanube\Precios\TiendanubePrecioImporte;
use App\Support\Tiendanube\Precios\TiendanubePrecioCsvColumnaCatalogo;

class TiendanubePrecioCsvFilaProductoService
{
    /**
     * @param  list<int>  $varianteIds
     * @return array<int, TiendanubeProductoVariante>
     */
    public function cargarVariantes(array $varianteIds): array
    {
        if ($varianteIds === []) {
            return [];
        }

        $variantes = TiendanubeProductoVariante::query()
            ->with(['producto.categorias.padre'])
            ->whereIn('id', $varianteIds)
            ->get();

        $mapa = [];
        foreach ($variantes as $variante) {
            $mapa[(int) $variante->id] = $variante;
        }

        return $mapa;
    }

    /**
     * @return array<string, string>
     */
    public function mapaDesdeEspejo(TiendanubeProductoVariante $variante): array
    {
        $producto = $variante->producto;
        $attrs = $this->atributosProducto($producto);

        return [
            TiendanubePrecioCsvColumnaCatalogo::IDENTIFICADOR_URL => $producto?->handleVisible() ?? '',
            TiendanubePrecioCsvColumnaCatalogo::NOMBRE => $producto?->nombreVisible() ?? '',
            TiendanubePrecioCsvColumnaCatalogo::CATEGORIAS => $this->categorias($producto),
            TiendanubePrecioCsvColumnaCatalogo::PRECIO => $this->importe($variante->getRawOriginal('price')),
            TiendanubePrecioCsvColumnaCatalogo::PRECIO_PROMOCIONAL => $this->importe($variante->getRawOriginal('promotional_price')),
            TiendanubePrecioCsvColumnaCatalogo::PESO => $this->numeroOpcional($variante->getRawOriginal('weight')),
            TiendanubePrecioCsvColumnaCatalogo::ALTO => '',
            TiendanubePrecioCsvColumnaCatalogo::ANCHO => '',
            TiendanubePrecioCsvColumnaCatalogo::PROFUNDIDAD => '',
            TiendanubePrecioCsvColumnaCatalogo::STOCK => $variante->stock === null ? '' : (string) $variante->stock,
            TiendanubePrecioCsvColumnaCatalogo::SKU => (string) ($variante->sku ?? ''),
            TiendanubePrecioCsvColumnaCatalogo::CODIGO_BARRAS => (string) ($variante->barcode ?? ''),
            TiendanubePrecioCsvColumnaCatalogo::MOSTRAR_TIENDA => $this->siNo((bool) ($producto?->published)),
            TiendanubePrecioCsvColumnaCatalogo::ENVIO_SIN_CARGO => $this->siNo((bool) ($producto?->free_shipping)),
            TiendanubePrecioCsvColumnaCatalogo::DESCRIPCION => $producto?->descripcionVisible() ?? '',
            TiendanubePrecioCsvColumnaCatalogo::TAGS => (string) ($producto?->tags ?? ''),
            TiendanubePrecioCsvColumnaCatalogo::SEO_TITULO => (string) ($producto?->seo_title ?? ''),
            TiendanubePrecioCsvColumnaCatalogo::SEO_DESCRIPCION => (string) ($producto?->seo_description ?? ''),
            TiendanubePrecioCsvColumnaCatalogo::MARCA => (string) ($producto?->brand ?? ''),
            TiendanubePrecioCsvColumnaCatalogo::PRODUCTO_FISICO => $this->siNo((bool) ($producto?->requires_shipping ?? true)),
            TiendanubePrecioCsvColumnaCatalogo::MPN => $attrs['mpn'],
            TiendanubePrecioCsvColumnaCatalogo::SEXO => $attrs['sexo'],
            TiendanubePrecioCsvColumnaCatalogo::RANGO_EDAD => $attrs['edad'],
            TiendanubePrecioCsvColumnaCatalogo::COSTO => $this->importe($variante->getRawOriginal('cost')),
            TiendanubePrecioCsvColumnaCatalogo::VISIBILIDAD => ($producto?->published) ? 'Visible' : 'No listado',
        ];
    }

    private function categorias(?TiendanubeProducto $producto): string
    {
        if (! $producto) {
            return '';
        }

        $rutas = [];
        foreach ($producto->categorias as $categoria) {
            $partes = [];
            $actual = $categoria;
            $guardia = 0;
            while ($actual && $guardia < 8) {
                array_unshift($partes, $actual->nombreVisible());
                $actual = $actual->padre;
                $guardia++;
            }
            if ($partes !== []) {
                $rutas[] = implode(' > ', $partes);
            }
        }

        return implode(', ', $rutas);
    }

    /**
     * @return array{mpn: string, sexo: string, edad: string}
     */
    private function atributosProducto(?TiendanubeProducto $producto): array
    {
        $salida = ['mpn' => '', 'sexo' => '', 'edad' => ''];
        $attrs = $producto?->attributes;
        if (! is_array($attrs)) {
            return $salida;
        }

        $mapa = [];
        if (array_is_list($attrs)) {
            foreach ($attrs as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $nombre = strtolower((string) ($item['es'] ?? $item['name'] ?? $item['key'] ?? ''));
                $valor = (string) ($item['value'] ?? $item['valor'] ?? '');
                if ($nombre !== '' && $valor !== '') {
                    $mapa[$nombre] = $valor;
                }
            }
        } else {
            foreach ($attrs as $clave => $valor) {
                $mapa[strtolower((string) $clave)] = is_array($valor)
                    ? (string) ($valor['es'] ?? $valor['es_MX'] ?? reset($valor) ?: '')
                    : (string) $valor;
            }
        }

        $salida['mpn'] = $mapa['mpn'] ?? $mapa['mpn (número de pieza del fabricante)'] ?? '';
        $salida['sexo'] = $mapa['sexo'] ?? $mapa['gender'] ?? $mapa['sex'] ?? '';
        $salida['edad'] = $mapa['rango de edad'] ?? $mapa['age_group'] ?? $mapa['age group'] ?? '';

        return $salida;
    }

    private function importe(mixed $raw): string
    {
        $formateado = TiendanubePrecioImporte::formatStored($raw);

        return $formateado ?? '';
    }

    private function numeroOpcional(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        return rtrim(rtrim((string) $raw, '0'), '.') ?: '0';
    }

    private function siNo(bool $valor): string
    {
        return $valor ? 'SÍ' : 'NO';
    }
}
