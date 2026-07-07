<?php

namespace App\Services\WooCommerce;

use App\Models\Woocommerce\WoocommerceConfiguracion;
use App\Models\Woocommerce\WoocommerceMargin;
use App\Models\Woocommerce\WoocommerceProduct;
use Rap2hpoutre\FastExcel\FastExcel;

class WooCommercePreciosService
{
    /** Columnas de precio base (prioridad legacy). Plataformas = columna F legacy / Lista de Resurtido. */
    private const COLUMNAS_PRECIO_BASE = ['plataformas', 'pg', 'costocalculado', 'costowizerp'];

    public function obtenerIva(): float
    {
        return (float) (WoocommerceConfiguracion::obtener()->iva ?? 1.16);
    }

    /**
     * Lee cabeceras del Excel. Si no hay fila de cabecera reconocible, genera etiquetas sintéticas.
     *
     * @return array{headers: string[], sin_cabecera: bool}
     */
    public function leerCabeceras(string $rutaArchivo): array
    {
        $filas = (new FastExcel)->import($rutaArchivo);
        $primeraFila = $filas[0] ?? null;

        if ($primeraFila === null) {
            $filasSinCabecera = (new FastExcel)->withoutHeaders()->import($rutaArchivo);
            $primeraFila = $filasSinCabecera[0] ?? null;
            if ($primeraFila === null) {
                return ['headers' => [], 'sin_cabecera' => true];
            }

            $maxCols = max(count($primeraFila), 10);
            $sinteticos = [];
            for ($i = 0; $i < $maxCols; $i++) {
                $sinteticos[] = $this->etiquetaColumnaSintetica($i);
            }

            return ['headers' => $sinteticos, 'sin_cabecera' => true];
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_keys($primeraFila));

        if ($this->pareceFilaDeDatos($headers, $primeraFila)) {
            $maxCols = max(count($primeraFila), 10);
            $sinteticos = [];
            for ($i = 0; $i < $maxCols; $i++) {
                $sinteticos[] = $this->etiquetaColumnaSintetica($i);
            }

            return ['headers' => $sinteticos, 'sin_cabecera' => true];
        }

        return ['headers' => $headers, 'sin_cabecera' => false];
    }

    /**
     * Sugiere mapeo a partir de cabeceras y configuración guardada.
     */
    public function sugerirMapeo(array $headers, ?array $mapeoGuardado = null): array
    {
        $mapeoGuardado = $mapeoGuardado ?? WoocommerceConfiguracion::obtener()->mapeoPreciosEfectivo();
        $sugerido = [
            'sku' => '',
            'precio_base' => '',
        ];

        if (in_array($mapeoGuardado['sku'], $headers, true)) {
            $sugerido['sku'] = $mapeoGuardado['sku'];
        }
        if (in_array($mapeoGuardado['precio_base'], $headers, true)) {
            $sugerido['precio_base'] = $mapeoGuardado['precio_base'];
        }

        foreach ($headers as $header) {
            $lower = mb_strtolower(trim((string) $header));
            if ($sugerido['sku'] === '' && (str_contains($lower, 'sku') || (str_contains($lower, 'codigo') && ! str_contains($lower, 'barras')))) {
                $sugerido['sku'] = $header;
            }
            if ($sugerido['precio_base'] === '' && (str_contains($lower, 'plataforma') || str_contains($lower, 'precio') || str_contains($lower, 'costo'))) {
                $sugerido['precio_base'] = $header;
            }
        }

        return $sugerido;
    }

    /**
     * Extrae SKU → precio base desde Excel.
     *
     * @param  array{sku: string, precio_base: string}|null  $mapping
     */
    public function extraerPreciosDesdeExcel(string $rutaArchivo, ?array $mapping = null): array
    {
        if ($mapping !== null && ! empty($mapping['sku']) && ! empty($mapping['precio_base'])) {
            return $this->extraerPreciosConMapeo($rutaArchivo, $mapping);
        }

        return $this->extraerPreciosLegacy($rutaArchivo);
    }

    /**
     * Previsualiza las primeras filas mapeadas.
     *
     * @param  array{sku: string, precio_base: string}  $mapping
     * @return array<int, array{sku: string, precio_base: float|null, advertencia: string|null}>
     */
    public function previsualizarMapeo(string $rutaArchivo, array $mapping, int $limite = 5): array
    {
        $cabeceras = $this->leerCabeceras($rutaArchivo);
        $filas = $cabeceras['sin_cabecera']
            ? (new FastExcel)->withoutHeaders()->import($rutaArchivo)
            : (new FastExcel)->import($rutaArchivo);

        $muestra = [];
        foreach (collect($filas)->take($limite) as $linea) {
            $fila = $cabeceras['sin_cabecera']
                ? $this->filaNumericaAAsociativa($linea)
                : $linea;

            $sku = trim((string) $this->valorColumnaMapeada($fila, $mapping['sku']));
            $precioRaw = $this->valorColumnaMapeada($fila, $mapping['precio_base']);
            $precio = $this->parsePrecioNumerico($precioRaw);

            $advertencia = null;
            if ($sku === '') {
                $advertencia = 'SKU vacío';
            } elseif ($precio <= 0) {
                $advertencia = 'Precio base inválido o vacío';
            }

            $muestra[] = [
                'sku' => $sku,
                'precio_base' => $precio > 0 ? $precio : null,
                'advertencia' => $advertencia,
            ];
        }

        return $muestra;
    }

    public function calcular(float $base, string $tipo, $margenes, float $iva): float
    {
        $mult = 1.0;
        foreach ($margenes as $m) {
            if ($base >= $m->precio_min && $base <= $m->precio_max) {
                $mult = ($tipo === 'rebaja') ? $m->multiplicador_rebaja : $m->multiplicador_normal;
                break;
            }
        }

        return round(($base * $mult) / $iva, 2);
    }

    public function generarAnalisisDeCambios(array $preciosWizerp, ?float $iva = null, $margenes = null): array
    {
        $iva = $iva ?? $this->obtenerIva();
        $margenes = $margenes ?? WoocommerceMargin::orderBy('precio_min')->get();
        $cambios = [];

        foreach (WoocommerceProduct::all() as $prod) {
            $precioBase = $this->resolverPrecioPorSku($preciosWizerp, $prod->sku);
            if ($precioBase === null) {
                continue;
            }

            $normal = $this->calcular($precioBase, 'normal', $margenes, $iva);
            $rebaja = $this->calcular($precioBase, 'rebaja', $margenes, $iva);

            if ($prod->precio_normal != $normal || $prod->precio_rebajado != $rebaja) {
                $cambios[] = [
                    'sku' => $prod->sku,
                    'nombre' => $prod->nombre,
                    'precio_normal_anterior' => $prod->precio_normal,
                    'precio_normal_nuevo' => $normal,
                    'precio_rebaja_anterior' => $prod->precio_rebajado,
                    'precio_rebaja_nuevo' => $rebaja,
                ];
            }
        }

        return $cambios;
    }

    /**
     * Cruce flexible de SKU (con/sin ceros a la izquierda).
     */
    public function resolverPrecioPorSku(array $precios, string $skuCatalogo): ?float
    {
        $sku = trim($skuCatalogo);
        if ($sku === '') {
            return null;
        }

        if (isset($precios[$sku])) {
            return $precios[$sku];
        }

        $sinCeros = ltrim($sku, '0');
        if ($sinCeros !== '' && isset($precios[$sinCeros])) {
            return $precios[$sinCeros];
        }

        foreach ($precios as $clave => $valor) {
            if (ltrim((string) $clave, '0') === $sinCeros) {
                return $valor;
            }
        }

        return null;
    }

    /**
     * @param  array{sku: string, precio_base: string}  $mapping
     */
    private function extraerPreciosConMapeo(string $rutaArchivo, array $mapping): array
    {
        $precios = [];
        $cabeceras = $this->leerCabeceras($rutaArchivo);
        $filas = $cabeceras['sin_cabecera']
            ? (new FastExcel)->withoutHeaders()->import($rutaArchivo)
            : (new FastExcel)->import($rutaArchivo);

        foreach ($filas as $linea) {
            $fila = $cabeceras['sin_cabecera']
                ? $this->filaNumericaAAsociativa($linea)
                : $linea;

            $sku = trim((string) $this->valorColumnaMapeada($fila, $mapping['sku']));
            $precio = $this->parsePrecioNumerico($this->valorColumnaMapeada($fila, $mapping['precio_base']));

            if ($sku !== '' && $precio > 0) {
                $precios[$sku] = $precio;
            }
        }

        return $precios;
    }

    private function extraerPreciosLegacy(string $rutaArchivo): array
    {
        $precios = [];

        (new FastExcel)->import($rutaArchivo, function ($linea) use (&$precios) {
            $normalizada = $this->normalizarFila($linea);
            $sku = $this->extraerSku($normalizada);
            $precio = $this->extraerPrecioBase($normalizada);

            if ($sku !== '' && $precio > 0) {
                $precios[$sku] = $precio;
            }
        });

        if (! empty($precios)) {
            return $precios;
        }

        (new FastExcel)->withoutHeaders()->import($rutaArchivo, function ($linea) use (&$precios) {
            $sku = trim((string) ($linea[1] ?? ''));
            $precio = $this->parsePrecioNumerico($linea[5] ?? 0);

            if ($sku !== '' && $precio > 0) {
                $precios[$sku] = $precio;
            }
        });

        return $precios;
    }

    private function pareceFilaDeDatos(array $headers, array $fila): bool
    {
        $headersNormalizados = array_map(fn ($h) => $this->normalizarClaveColumna((string) $h), $headers);
        $tieneCabeceraConocida = count(array_intersect($headersNormalizados, ['sku', 'folio', 'descripcion', 'plataformas', 'pg'])) > 0;

        if ($tieneCabeceraConocida) {
            return false;
        }

        $primerHeader = $headers[0] ?? '';
        if (is_numeric($primerHeader)) {
            return true;
        }

        $valores = array_values($fila);
        $numericos = 0;
        foreach ($valores as $valor) {
            if (is_numeric($valor) || $this->parsePrecioNumerico($valor) > 0) {
                $numericos++;
            }
        }

        return $numericos >= max(1, (int) floor(count($valores) / 2));
    }

    private function etiquetaColumnaSintetica(int $indice): string
    {
        $letra = '';
        $n = $indice;
        do {
            $letra = chr(65 + ($n % 26)) . $letra;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return 'Columna ' . $letra;
    }

    /**
     * @param  array<int|string, mixed>  $fila
     */
    private function filaNumericaAAsociativa(array $fila): array
    {
        $asociativa = [];
        $indice = 0;
        foreach ($fila as $valor) {
            $asociativa[$this->etiquetaColumnaSintetica($indice)] = $valor;
            $indice++;
        }

        return $asociativa;
    }

    /**
     * @param  array<int|string, mixed>  $fila
     */
    private function valorColumnaMapeada(array $fila, string $columna): mixed
    {
        if (array_key_exists($columna, $fila)) {
            return $fila[$columna];
        }

        if (preg_match('/^Columna ([A-Z]+)$/i', $columna, $matches)) {
            $indice = $this->indiceDesdeLetraColumna(strtoupper($matches[1]));

            return $fila[$indice] ?? $fila[$this->etiquetaColumnaSintetica($indice)] ?? null;
        }

        foreach ($fila as $clave => $valor) {
            if ($this->normalizarClaveColumna((string) $clave) === $this->normalizarClaveColumna($columna)) {
                return $valor;
            }
        }

        return null;
    }

    private function indiceDesdeLetraColumna(string $letra): int
    {
        $indice = 0;
        $len = strlen($letra);
        for ($i = 0; $i < $len; $i++) {
            $indice = $indice * 26 + (ord($letra[$i]) - 64);
        }

        return $indice - 1;
    }

    private function normalizarFila(array $linea): array
    {
        $normalizada = [];
        foreach ($linea as $clave => $valor) {
            $normalizada[$this->normalizarClaveColumna((string) $clave)] = $valor;
        }

        return $normalizada;
    }

    private function normalizarClaveColumna(string $clave): string
    {
        $clave = mb_strtolower(trim($clave));
        $clave = str_replace([' ', '_', '-'], '', $clave);
        $clave = strtr($clave, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);

        return $clave;
    }

    private function extraerSku(array $linea): string
    {
        $sku = $linea['sku'] ?? '';

        return trim((string) $sku);
    }

    private function extraerPrecioBase(array $linea): float
    {
        foreach (self::COLUMNAS_PRECIO_BASE as $columna) {
            if (array_key_exists($columna, $linea)) {
                $precio = $this->parsePrecioNumerico($linea[$columna]);
                if ($precio > 0) {
                    return $precio;
                }
            }
        }

        return 0.0;
    }

    private function parsePrecioNumerico(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $limpio = str_replace(['$', ',', ' '], '', (string) $valor);

        return is_numeric($limpio) ? (float) $limpio : 0.0;
    }
}
