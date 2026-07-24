<?php

namespace App\Services\FuncionesOperativas;

use Rap2hpoutre\FastExcel\FastExcel;

class CruceAvisoMercanciaService
{
    /** Layout actual del Drive: FECHA(vacía), UPC, NOMBRE PRODUCTO, PEDIDO SUGERIDO, PRECIO, VENDEDOR, CLIENTE */
    private const FALLBACK_AVISO = [
        'upc' => 1,
        'vendedor' => 5,
        'cliente' => 6,
    ];

    /**
     * @return array{resultados: list<array<string, mixed>>, avisos: int, compra: int}
     */
    public function cruzar(string $rutaAviso, string $rutaCompra): array
    {
        $diccionario = $this->cargarAvisos($rutaAviso);
        $resultados = [];
        $filasCompra = 0;
        $leyendoDatos = false;
        $indices = [];

        (new FastExcel)->withoutHeaders()->import($rutaCompra, function ($linea) use (&$resultados, &$filasCompra, &$leyendoDatos, &$indices, $diccionario) {
            $valores = array_values($linea);

            if (! $leyendoDatos) {
                $mapa = $this->indicesCompra($valores);
                if ($mapa !== null) {
                    $leyendoDatos = true;
                    $indices = $mapa;
                }

                return;
            }

            $skuCrudo = $this->celda($linea, $indices['sku']);
            $skuLimpio = $this->normalizarCodigo($skuCrudo);
            $recibido = (int) $this->celda($linea, $indices['recibido']);
            $filasCompra++;

            if ($skuLimpio !== '' && isset($diccionario[$skuLimpio]) && $recibido > 0) {
                $resultados[] = [
                    'SKU' => $skuCrudo,
                    'Descripción' => $this->celda($linea, $indices['descripcion']) ?: 'SIN DESCRIPCION',
                    'Piezas Recibidas' => $recibido,
                    'Vendedor Asignado' => $diccionario[$skuLimpio]['vendedor'],
                    'Clientes en Espera' => $diccionario[$skuLimpio]['cliente'],
                ];
            }
        });

        return [
            'resultados' => $resultados,
            'avisos' => count($diccionario),
            'compra' => $filasCompra,
        ];
    }

    /** @return array<string, array{vendedor: string, cliente: string}> */
    public function cargarAvisos(string $rutaAviso): array
    {
        $diccionario = [];
        $indices = null;

        (new FastExcel)->withoutHeaders()->import($rutaAviso, function ($linea) use (&$diccionario, &$indices) {
            $valores = array_values($linea);

            if ($indices === null) {
                $detectados = $this->indicesAviso($valores);
                if ($detectados !== null) {
                    $indices = $detectados;
                }

                return;
            }

            $upc = $this->normalizarCodigo($this->celda($linea, $indices['upc']));
            if ($upc === '') {
                return;
            }

            $vendedor = trim((string) $this->celda($linea, $indices['vendedor']));
            $cliente = trim((string) $this->celda($linea, $indices['cliente']));

            $diccionario[$upc] = [
                'vendedor' => $vendedor !== '' ? $vendedor : 'SIN ASIGNAR',
                'cliente' => $cliente !== '' ? $cliente : 'SIN DATOS',
            ];
        });

        // Fallback posicional si nunca hubo fila de encabezados reconocible
        if ($indices === null) {
            (new FastExcel)->withoutHeaders()->import($rutaAviso, function ($linea) use (&$diccionario) {
                $valores = array_values($linea);
                if ($this->indicesAviso($valores) !== null) {
                    return;
                }

                $upc = $this->normalizarCodigo($valores[self::FALLBACK_AVISO['upc']] ?? '');
                if ($upc === '') {
                    return;
                }

                $vendedor = trim((string) ($valores[self::FALLBACK_AVISO['vendedor']] ?? ''));
                $cliente = trim((string) ($valores[self::FALLBACK_AVISO['cliente']] ?? ''));
                $diccionario[$upc] = [
                    'vendedor' => $vendedor !== '' ? $vendedor : 'SIN ASIGNAR',
                    'cliente' => $cliente !== '' ? $cliente : 'SIN DATOS',
                ];
            });
        }

        return $diccionario;
    }

    public function normalizarCodigo(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if ($valor instanceof \DateTimeInterface) {
            return '';
        }

        if (is_float($valor) || (is_int($valor))) {
            $valor = sprintf('%.0f', $valor);
        } else {
            $valor = trim((string) $valor);
            if ($valor === '' || strcasecmp($valor, '#N/A') === 0) {
                return '';
            }
            // Excel / Sheets a veces entrega notación científica como texto
            if (preg_match('/^\d+\.?\d*[eE][+-]?\d+$/', $valor)) {
                $valor = sprintf('%.0f', (float) $valor);
            } elseif (preg_match('/^\d+\.0+$/', $valor)) {
                $valor = explode('.', $valor, 2)[0];
            }
        }

        $valor = preg_replace('/[^\d]/', '', (string) $valor) ?? '';

        return ltrim($valor, '0');
    }

    /** @param list<mixed> $valores */
    private function indicesAviso(array $valores): ?array
    {
        $norm = array_map(fn ($v) => $this->normalizarEncabezado($v), $valores);

        $upc = $this->buscarIndice($norm, ['upc', 'sku', 'ean', 'codigo', 'codigo de barras', 'codigodebarras', 'barcode']);
        if ($upc === null) {
            return null;
        }

        return [
            'upc' => $upc,
            'vendedor' => $this->buscarIndice($norm, ['vendedor', 'vendedora', 'asesor']) ?? self::FALLBACK_AVISO['vendedor'],
            'cliente' => $this->buscarIndice($norm, ['cliente', 'clientes', 'cliente en espera', 'clientes en espera']) ?? self::FALLBACK_AVISO['cliente'],
        ];
    }

    /** @param list<mixed> $valores */
    private function indicesCompra(array $valores): ?array
    {
        $norm = array_map(fn ($v) => $this->normalizarEncabezado($v), $valores);

        $sku = $this->buscarIndice($norm, ['sku']);
        $recibido = $this->buscarIndice($norm, ['recibido']);
        if ($sku === null || $recibido === null) {
            return null;
        }

        $descripcion = $this->buscarIndice($norm, ['descripcion', 'descripción', 'producto', 'nombre']);

        return [
            'sku' => $sku,
            'descripcion' => $descripcion ?? 1,
            'recibido' => $recibido,
        ];
    }

    /** @param list<string> $normalizados */
    private function buscarIndice(array $normalizados, array $aliases): ?int
    {
        foreach ($normalizados as $i => $nombre) {
            if ($nombre !== '' && in_array($nombre, $aliases, true)) {
                return $i;
            }
        }

        return null;
    }

    private function normalizarEncabezado(mixed $valor): string
    {
        $texto = mb_strtolower(trim((string) $valor));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'ü' => 'u',
        ]);

        return preg_replace('/\s+/', ' ', $texto) ?? '';
    }

    private function celda(array $linea, int $indice): string
    {
        $valores = array_values($linea);

        return trim((string) ($valores[$indice] ?? ''));
    }
}
