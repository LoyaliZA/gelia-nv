<?php

namespace App\Services\GeliaAi;

use Rap2hpoutre\FastExcel\FastExcel;

class InspeccionarArchivoGeliaAiService
{
    /** Tope de filas a escanear solo para headers/mapping; no hace falta el total exacto para el LLM. */
    private const PEEK_ROWS = 3;

    /**
     * @return array{kind: string, headers: list<string>, rows: int, guess_mapping: array<string, string>}
     */
    public function inspeccionar(string $absolutePath, string $originalName = ''): array
    {
        $kind = $this->detectarKind([], $originalName);
        $headers = [];
        $rows = 0;
        $peeked = 0;
        $scannedFully = false;

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION) ?: '');

        // CSV: peek barato (pocas líneas). Conteos exactos no van al LLM.
        if ($ext === 'csv' && is_readable($absolutePath)) {
            $fh = fopen($absolutePath, 'r');
            if ($fh) {
                $first = fgetcsv($fh);
                if (is_array($first)) {
                    // Wizerp/listados: primera fila suele ser título, no headers útiles.
                    $joined = mb_strtolower(implode(' ', array_map('strval', $first)));
                    if (! preg_match('/\b(sku|codigo|código|existencia|costo|precio)\b/u', $joined)) {
                        $headers = [];
                    } else {
                        $headers = array_values(array_filter(array_map(
                            fn ($k) => trim((string) $k),
                            $first
                        ), fn ($k) => $k !== ''));
                    }
                }
                // Contar líneas restantes de forma ligera (sin parsear celdas).
                $rows = 0;
                while (! feof($fh)) {
                    $line = fgets($fh);
                    if ($line !== false && trim($line) !== '') {
                        $rows++;
                    }
                }
                fclose($fh);
                $peeked = 1;
                $scannedFully = true; // conteo de líneas CSV, no celdas
            }
        } else {
            // XLSX/XLS: solo peek de pocas filas para headers; no contar todo el libro.
            try {
                $collection = (new FastExcel)->import($absolutePath);
                foreach ($collection as $row) {
                    if ($peeked === 0 && is_array($row)) {
                        $headers = array_map(fn ($k) => trim((string) $k), array_keys($row));
                    }
                    $peeked++;
                    if ($peeked >= self::PEEK_ROWS) {
                        break;
                    }
                }
                $rows = $peeked; // cota inferior; suficiente para UI
            } catch (\Throwable) {
                $headers = [];
                $rows = 0;
            }
        }

        if ($kind === 'desconocido') {
            $kind = $this->detectarKind($headers, $originalName);
        }
        $guess = $this->adivinarMapping($headers);

        return [
            'kind' => $kind,
            'headers' => $headers,
            'rows' => $rows,
            'guess_mapping' => $guess,
        ];
    }

    /**
     * @param  list<string>  $headers
     */
    public function detectarKind(array $headers, string $originalName = ''): string
    {
        $h = array_map(fn ($x) => mb_strtolower(trim($x)), $headers);
        $joined = ' '.implode(' ', $h).' ';
        $name = mb_strtolower($originalName);

        if (str_contains($name, 'existencia') || str_contains($name, 'existencias') || str_contains($name, 'inventario')) {
            return 'existencias';
        }
        if (str_contains($name, 'precio') || str_contains($name, 'lista')) {
            return 'precios';
        }
        if (str_contains($name, 'costo')) {
            return 'costos';
        }

        $hasSku = $this->headerMatch($h, ['sku', 'codigo', 'código', 'codigo_del_producto']);
        $hasCosto = $this->headerMatch($h, ['costo', 'costo_reposicion', 'cost', 'costo']);
        $hasExistencia = $this->headerMatch($h, ['existencia', 'existencias', 'stock', 'cantidad']);
        $hasPrecio = $this->headerMatch($h, ['precio', 'pg', 'precio_venta', 'p_venta']);

        if ($hasSku && $hasCosto && ! $hasExistencia) {
            return 'costos';
        }
        if ($hasSku && $hasExistencia) {
            return 'existencias';
        }
        if ($hasSku && $hasPrecio) {
            return 'precios';
        }
        if (str_contains($joined, 'existencia')) {
            return 'existencias';
        }

        return 'desconocido';
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    public function adivinarMapping(array $headers): array
    {
        $map = [];
        foreach ($headers as $header) {
            $n = mb_strtolower(trim($header));
            if ($n === '') {
                continue;
            }
            if (! isset($map['sku']) && preg_match('/^(sku|codigo|código|codigo_del_producto|cod)$/u', $n)) {
                $map['sku'] = $header;
            }
            if (! isset($map['descripcion']) && preg_match('/descrip|nombre|producto/u', $n)) {
                $map['descripcion'] = $header;
            }
            if (! isset($map['existencia']) && preg_match('/existencia|stock|cantidad/u', $n)) {
                $map['existencia'] = $header;
            }
            if (! isset($map['costo']) && preg_match('/^costo$|costo_unit|cost$/u', $n) && ! str_contains($n, 'repos')) {
                $map['costo'] = $header;
            }
            if (! isset($map['costo_reposicion']) && str_contains($n, 'repos')) {
                $map['costo_reposicion'] = $header;
            }
            if (! isset($map['precio_venta']) && preg_match('/precio|pg|p_venta/u', $n)) {
                $map['precio_venta'] = $header;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $needles
     */
    private function headerMatch(array $headers, array $needles): bool
    {
        foreach ($headers as $h) {
            foreach ($needles as $n) {
                if ($h === $n || str_contains($h, $n)) {
                    return true;
                }
            }
        }

        return false;
    }
}
