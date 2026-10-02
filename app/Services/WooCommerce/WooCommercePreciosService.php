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

    /** @var array<string, string> Claves internas → encabezado CSV (importación Woo / precios locales). */
    public const COLUMNAS_CSV_EXPORT = [
        'sku' => 'SKU',
        'nombre' => 'Nombre',
        'precio_rebajado' => 'Precio rebajado',
        'precio_normal' => 'Precio normal',
    ];

    private const ORDEN_COLUMNAS_CSV_EXPORT = ['sku', 'nombre', 'precio_rebajado', 'precio_normal'];

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

    /**
     * Sugiere mapeo para Contabilidad (SKU, descripción, precio base con prioridad Bronce).
     *
     * @param  array{sku?: string, precio_base?: string, descripcion?: string}  $mapeoGuardado
     * @return array{sku: string, precio_base: string, descripcion: string}
     */
    public function sugerirMapeoContabilidad(array $headers, array $mapeoGuardado): array
    {
        $sugerido = [
            'sku' => '',
            'precio_base' => '',
            'descripcion' => '',
        ];

        foreach (['sku', 'precio_base', 'descripcion'] as $campo) {
            $valorGuardado = $mapeoGuardado[$campo] ?? '';
            if ($valorGuardado !== '' && in_array($valorGuardado, $headers, true)) {
                $sugerido[$campo] = $valorGuardado;
            }
        }

        foreach ($headers as $header) {
            $lower = mb_strtolower(trim((string) $header));
            if ($sugerido['sku'] === '' && (str_contains($lower, 'sku') || (str_contains($lower, 'codigo') && ! str_contains($lower, 'barras')))) {
                $sugerido['sku'] = $header;
            }
            if ($sugerido['descripcion'] === '' && (str_contains($lower, 'descripcion') || str_contains($lower, 'descrip') || str_contains($lower, 'nombre') || str_contains($lower, 'producto'))) {
                $sugerido['descripcion'] = $header;
            }
        }

        if ($sugerido['precio_base'] === '') {
            foreach ($headers as $header) {
                if ($this->normalizarClaveColumna((string) $header) === 'bronce') {
                    $sugerido['precio_base'] = $header;
                    break;
                }
            }
        }

        if ($sugerido['precio_base'] === '') {
            foreach ($headers as $header) {
                $lower = mb_strtolower(trim((string) $header));
                if (str_contains($lower, 'plataforma') || str_contains($lower, 'precio') || str_contains($lower, 'costo') || str_contains($lower, 'bronce')) {
                    $sugerido['precio_base'] = $header;
                    break;
                }
            }
        }

        return $sugerido;
    }

    /**
     * @param  array{sku: string, precio_base: string, descripcion?: string}  $mapping
     * @return array<int, array{sku: string, descripcion: string|null, precio_base: float|null, advertencia: string|null}>
     */
    public function previsualizarMapeoContabilidad(string $rutaArchivo, array $mapping, int $limite = 5): array
    {
        $cabeceras = $this->leerCabeceras($rutaArchivo);
        $filas = $cabeceras['sin_cabecera']
            ? (new FastExcel)->withoutHeaders()->import($rutaArchivo)
            : (new FastExcel)->import($rutaArchivo);

        $columnaDescripcion = $mapping['descripcion'] ?? '';
        $muestra = [];

        foreach (collect($filas)->take($limite) as $linea) {
            $fila = $cabeceras['sin_cabecera']
                ? $this->filaNumericaAAsociativa($linea)
                : $linea;

            $sku = trim((string) $this->valorColumnaMapeada($fila, $mapping['sku']));
            $precio = $this->parsePrecioNumerico($this->valorColumnaMapeada($fila, $mapping['precio_base']));
            $descripcion = $columnaDescripcion !== ''
                ? trim((string) $this->valorColumnaMapeada($fila, $columnaDescripcion))
                : '';

            $advertencia = null;
            if ($sku === '') {
                $advertencia = 'SKU vacío';
            } elseif ($precio <= 0) {
                $advertencia = 'Precio base inválido o vacío';
            }

            $muestra[] = [
                'sku' => $sku,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'precio_base' => $precio > 0 ? $precio : null,
                'advertencia' => $advertencia,
            ];
        }

        return $muestra;
    }

    /**
     * @param  array{sku: string, precio_base: string, descripcion?: string}  $mapping
     * @return array<string, array{nombre: string, precio: float}>
     */
    public function extraerDiccionarioContabilidad(string $rutaArchivo, array $mapping): array
    {
        $diccionario = [];
        $cabeceras = $this->leerCabeceras($rutaArchivo);
        $filas = $cabeceras['sin_cabecera']
            ? (new FastExcel)->withoutHeaders()->import($rutaArchivo)
            : (new FastExcel)->import($rutaArchivo);

        $columnaDescripcion = $mapping['descripcion'] ?? '';

        foreach ($filas as $linea) {
            $fila = $cabeceras['sin_cabecera']
                ? $this->filaNumericaAAsociativa($linea)
                : $linea;

            $sku = trim((string) $this->valorColumnaMapeada($fila, $mapping['sku']));
            $precio = $this->parsePrecioNumerico($this->valorColumnaMapeada($fila, $mapping['precio_base']));

            if ($sku === '' || $precio <= 0) {
                continue;
            }

            $nombre = 'Producto Desconocido';
            if ($columnaDescripcion !== '') {
                $nombreRaw = trim((string) $this->valorColumnaMapeada($fila, $columnaDescripcion));
                if ($nombreRaw !== '') {
                    $nombre = $nombreRaw;
                }
            }

            $diccionario[$sku] = [
                'nombre' => $nombre,
                'precio' => $precio,
            ];
        }

        return $diccionario;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generarAnalisisDeCambios(
        array $preciosWizerp,
        ?float $iva = null,
        $margenes = null,
        ?callable $alAvanzarCatalogo = null
    ): array {
        $iva = $iva ?? $this->obtenerIva();
        $margenes = $margenes ?? WoocommerceMargin::orderBy('precio_min')->get();
        $cambios = [];
        $indicePrecios = $this->indicePreciosPorSku($preciosWizerp);
        $totalCatalogo = WoocommerceProduct::count();
        $catalogoProcesados = 0;

        foreach (
            WoocommerceProduct::query()
                ->select(['sku', 'nombre', 'precio_normal', 'precio_rebajado'])
                ->lazy(1000) as $prod
        ) {
            $catalogoProcesados++;
            if ($alAvanzarCatalogo !== null && ($catalogoProcesados === $totalCatalogo || $catalogoProcesados % 25 === 0)) {
                $alAvanzarCatalogo($catalogoProcesados, $totalCatalogo);
            }

            $precioBase = $this->resolverPrecioPorSku($preciosWizerp, $prod->sku, $indicePrecios);
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

        if ($alAvanzarCatalogo !== null && $totalCatalogo > 0) {
            $alAvanzarCatalogo($catalogoProcesados, $totalCatalogo);
        }

        return $cambios;
    }

    /**
     * @param  list<string>|null  $columnas
     * @return list<string>
     */
    public function normalizarColumnasExport(?array $columnas): array
    {
        $permitidas = self::ORDEN_COLUMNAS_CSV_EXPORT;
        $seleccion = $columnas !== null && $columnas !== []
            ? array_values(array_intersect($permitidas, $columnas))
            : $permitidas;

        if (! in_array('sku', $seleccion, true)) {
            array_unshift($seleccion, 'sku');
        }

        $tieneDatoUtil = count(array_intersect($seleccion, ['nombre', 'precio_normal', 'precio_rebajado'])) > 0;
        if (! $tieneDatoUtil) {
            throw new \InvalidArgumentException('Selecciona al menos una columna además de SKU (nombre o precios).');
        }

        return array_values(array_intersect($permitidas, $seleccion));
    }

    /**
     * @param  list<array<string, mixed>>  $cambios
     */
    public function aplicarPreciosLocales(array $cambios, ?callable $alAvanzar = null): int
    {
        if ($cambios === []) {
            return 0;
        }

        $skus = [];
        foreach ($cambios as $cambio) {
            $sku = trim((string) ($cambio['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }

        if ($skus === []) {
            return 0;
        }

        $productosPorSku = WoocommerceProduct::query()
            ->whereIn('sku', array_keys($skus))
            ->get(['id', 'sku', 'nombre', 'tipo', 'parent_id'])
            ->keyBy('sku');

        $filas = [];
        $ahora = now();
        foreach ($cambios as $cambio) {
            $sku = trim((string) ($cambio['sku'] ?? ''));
            $producto = $productosPorSku->get($sku);
            if ($producto === null) {
                continue;
            }

            $filas[] = [
                'id' => $producto->id,
                'sku' => $sku,
                'nombre' => (string) ($cambio['nombre'] ?? $producto->nombre),
                'tipo' => $producto->tipo,
                'parent_id' => $producto->parent_id,
                'precio_normal' => $cambio['precio_normal_nuevo'],
                'precio_rebajado' => $cambio['precio_rebaja_nuevo'],
                'updated_at' => $ahora,
            ];
        }

        $actualizados = 0;
        $totalFilas = count($filas);
        foreach (array_chunk($filas, 500) as $lote) {
            WoocommerceProduct::upsert($lote, ['id'], ['precio_normal', 'precio_rebajado', 'updated_at']);
            $actualizados += count($lote);
            if ($alAvanzar !== null) {
                $alAvanzar($actualizados, $totalFilas);
            }
        }

        return $actualizados;
    }

    /**
     * @param  list<array<string, mixed>>  $cambios
     * @param  list<string>  $columnasClave
     * @return array{filas: int, tamano_bytes: int}
     */
    public function escribirCsvExportacion(string $rutaAbsoluta, array $cambios, array $columnasClave): array
    {
        $columnasOrdenadas = array_values(array_intersect(self::ORDEN_COLUMNAS_CSV_EXPORT, $columnasClave));
        $header = array_map(fn (string $clave) => self::COLUMNAS_CSV_EXPORT[$clave], $columnasOrdenadas);

        $out = fopen($rutaAbsoluta, 'w');
        if ($out === false) {
            throw new \RuntimeException('No se pudo crear el archivo CSV temporal.');
        }

        fputcsv($out, $header);
        $filas = 0;
        foreach ($cambios as $cambio) {
            $fila = [];
            foreach ($columnasOrdenadas as $clave) {
                $fila[] = match ($clave) {
                    'sku' => $cambio['sku'],
                    'nombre' => $cambio['nombre'],
                    'precio_rebajado' => $cambio['precio_rebaja_nuevo'],
                    'precio_normal' => $cambio['precio_normal_nuevo'],
                    default => null,
                };
            }
            fputcsv($out, $fila);
            $filas++;
        }
        fclose($out);

        return [
            'filas' => $filas,
            'tamano_bytes' => (int) filesize($rutaAbsoluta),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cambios
     * @param  list<string>  $columnasClave
     * @return array{header: list<string>, filas: list<list<string|float|null>>}
     */
    public function construirFilasCsvExport(array $cambios, array $columnasClave): array
    {
        $columnasOrdenadas = array_values(array_intersect(self::ORDEN_COLUMNAS_CSV_EXPORT, $columnasClave));
        $header = array_map(fn (string $clave) => self::COLUMNAS_CSV_EXPORT[$clave], $columnasOrdenadas);
        $filas = [];

        foreach ($cambios as $cambio) {
            $fila = [];
            foreach ($columnasOrdenadas as $clave) {
                $fila[] = match ($clave) {
                    'sku' => $cambio['sku'],
                    'nombre' => $cambio['nombre'],
                    'precio_rebajado' => $cambio['precio_rebaja_nuevo'],
                    'precio_normal' => $cambio['precio_normal_nuevo'],
                    default => null,
                };
            }
            $filas[] = $fila;
        }

        return ['header' => $header, 'filas' => $filas];
    }

    /**
     * Cruce flexible de SKU (con/sin ceros a la izquierda).
     */
    public function resolverPrecioPorSku(array $precios, string $skuCatalogo, ?array $indicePrecios = null): ?float
    {
        $sku = trim($skuCatalogo);
        if ($sku === '') {
            return null;
        }

        if ($indicePrecios !== null) {
            if (isset($indicePrecios[$sku])) {
                return (float) $indicePrecios[$sku];
            }

            $sinCeros = ltrim($sku, '0');
            if ($sinCeros !== '' && isset($indicePrecios[$sinCeros])) {
                return (float) $indicePrecios[$sinCeros];
            }

            return null;
        }

        if (isset($precios[$sku])) {
            return (float) $precios[$sku];
        }

        $sinCeros = ltrim($sku, '0');
        if ($sinCeros !== '' && isset($precios[$sinCeros])) {
            return (float) $precios[$sinCeros];
        }

        foreach ($precios as $clave => $valor) {
            if (ltrim((string) $clave, '0') === $sinCeros) {
                return (float) $valor;
            }
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private function indicePreciosPorSku(array $precios): array
    {
        $indice = [];

        foreach ($precios as $clave => $valor) {
            $sku = trim((string) $clave);
            if ($sku === '') {
                continue;
            }

            $precio = (float) $valor;
            if (! array_key_exists($sku, $indice)) {
                $indice[$sku] = $precio;
            }

            $sinCeros = ltrim($sku, '0');
            if ($sinCeros !== '' && ! array_key_exists($sinCeros, $indice)) {
                $indice[$sinCeros] = $precio;
            }
        }

        return $indice;
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
        $tieneCabeceraConocida = count(array_intersect($headersNormalizados, ['sku', 'folio', 'descripcion', 'plataformas', 'pg', 'bronce'])) > 0;

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
