<?php

namespace App\Support\Tiendanube\Precios;

final class TiendanubePrecioCsvColumnaCatalogo
{
    public const CONTRACT_VERSION = 'tn-csv-2026-09';

    public const IDENTIFICADOR_URL = 'Identificador de URL';

    public const NOMBRE = 'Nombre';

    public const CATEGORIAS = 'Categorías';

    public const PRECIO = 'Precio';

    public const PRECIO_PROMOCIONAL = 'Precio promocional';

    public const PESO = 'Peso (kg)';

    public const ALTO = 'Alto (cm)';

    public const ANCHO = 'Ancho (cm)';

    public const PROFUNDIDAD = 'Profundidad (cm)';

    public const STOCK = 'Stock';

    public const SKU = 'SKU';

    public const CODIGO_BARRAS = 'Código de barras';

    public const MOSTRAR_TIENDA = 'Mostrar en mi tienda en línea';

    public const ENVIO_SIN_CARGO = 'Envío sin cargo';

    public const DESCRIPCION = 'Descripción';

    public const TAGS = 'Tags';

    public const SEO_TITULO = 'Título para SEO';

    public const SEO_DESCRIPCION = 'Descripción para SEO';

    public const MARCA = 'Marca';

    public const PRODUCTO_FISICO = 'Producto Físico';

    public const MPN = 'MPN (Número de pieza del fabricante)';

    public const SEXO = 'Sexo';

    public const RANGO_EDAD = 'Rango de edad';

    public const COSTO = 'Costo';

    public const VISIBILIDAD = 'Visibilidad';

    public const PRESET_SOLO_PRECIOS = 'solo_precios';

    public const PRESET_IDENTIDAD_PRECIOS = 'identidad_precios';

    public const PRESET_PRODUCTO_COMPLETO = 'producto_completo';

    public const PRESET_PERSONALIZADO = 'personalizado';

    /**
     * @return list<string>
     */
    public static function encabezados(): array
    {
        return [
            self::IDENTIFICADOR_URL,
            self::NOMBRE,
            self::CATEGORIAS,
            self::PRECIO,
            self::PRECIO_PROMOCIONAL,
            self::PESO,
            self::ALTO,
            self::ANCHO,
            self::PROFUNDIDAD,
            self::STOCK,
            self::SKU,
            self::CODIGO_BARRAS,
            self::MOSTRAR_TIENDA,
            self::ENVIO_SIN_CARGO,
            self::DESCRIPCION,
            self::TAGS,
            self::SEO_TITULO,
            self::SEO_DESCRIPCION,
            self::MARCA,
            self::PRODUCTO_FISICO,
            self::MPN,
            self::SEXO,
            self::RANGO_EDAD,
            self::COSTO,
            self::VISIBILIDAD,
        ];
    }

    /**
     * @return list<string>
     */
    public static function identidadObligatoria(): array
    {
        return [self::IDENTIFICADOR_URL, self::SKU];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function presets(): array
    {
        return [
            self::PRESET_SOLO_PRECIOS => [
                self::IDENTIFICADOR_URL,
                self::SKU,
                self::PRECIO,
                self::PRECIO_PROMOCIONAL,
                self::COSTO,
            ],
            self::PRESET_IDENTIDAD_PRECIOS => [
                self::IDENTIFICADOR_URL,
                self::NOMBRE,
                self::CATEGORIAS,
                self::SKU,
                self::PRECIO,
                self::PRECIO_PROMOCIONAL,
                self::COSTO,
            ],
            self::PRESET_PRODUCTO_COMPLETO => self::encabezados(),
        ];
    }

    /**
     * @return list<array{clave: string, etiqueta: string, grupo: string}>
     */
    public static function metadatosUi(): array
    {
        $grupos = [
            self::IDENTIFICADOR_URL => 'identidad',
            self::SKU => 'identidad',
            self::NOMBRE => 'producto',
            self::CATEGORIAS => 'producto',
            self::PRECIO => 'precios',
            self::PRECIO_PROMOCIONAL => 'precios',
            self::COSTO => 'precios',
            self::PESO => 'inventario',
            self::ALTO => 'inventario',
            self::ANCHO => 'inventario',
            self::PROFUNDIDAD => 'inventario',
            self::STOCK => 'inventario',
            self::CODIGO_BARRAS => 'producto',
            self::MOSTRAR_TIENDA => 'producto',
            self::ENVIO_SIN_CARGO => 'producto',
            self::DESCRIPCION => 'producto',
            self::TAGS => 'producto',
            self::SEO_TITULO => 'producto',
            self::SEO_DESCRIPCION => 'producto',
            self::MARCA => 'producto',
            self::PRODUCTO_FISICO => 'producto',
            self::MPN => 'producto',
            self::SEXO => 'producto',
            self::RANGO_EDAD => 'producto',
            self::VISIBILIDAD => 'producto',
        ];

        $salida = [];
        foreach (self::encabezados() as $columna) {
            $salida[] = [
                'clave' => $columna,
                'etiqueta' => $columna,
                'grupo' => $grupos[$columna] ?? 'producto',
                'obligatoria' => in_array($columna, self::identidadObligatoria(), true),
            ];
        }

        return $salida;
    }

    /**
     * @param  list<string>  $columnas
     * @return list<string>
     */
    public static function ordenarCanonico(array $columnas): array
    {
        $elegidas = array_fill_keys($columnas, true);
        $ordenadas = [];
        foreach (self::encabezados() as $columna) {
            if (isset($elegidas[$columna])) {
                $ordenadas[] = $columna;
            }
        }

        return $ordenadas;
    }

    /**
     * @param  list<string>|null  $columnas
     * @return list<string>
     */
    public static function resolver(string $preset, ?array $columnas = null): array
    {
        if ($preset === self::PRESET_PERSONALIZADO || $columnas !== null) {
            $base = $columnas ?? [];
        } else {
            $presets = self::presets();
            $base = $presets[$preset] ?? $presets[self::PRESET_SOLO_PRECIOS];
        }

        foreach (self::identidadObligatoria() as $obligatoria) {
            if (! in_array($obligatoria, $base, true)) {
                $base[] = $obligatoria;
            }
        }

        $desconocidas = array_diff($base, self::encabezados());
        if ($desconocidas !== []) {
            throw new \InvalidArgumentException('Columnas desconocidas: '.implode(', ', $desconocidas));
        }

        return self::ordenarCanonico(array_values(array_unique($base)));
    }

    public static function esIdentidad(string $columna): bool
    {
        return in_array($columna, self::identidadObligatoria(), true);
    }

    public static function admiteIntencionD(string $columna): bool
    {
        return in_array($columna, [self::PRECIO, self::PRECIO_PROMOCIONAL, self::COSTO], true);
    }
}
