<?php

namespace App\Services\Almacenes;

use App\Models\Almacenes\ImportacionAlmacenLog;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class LeerFilasImportacionAlmacenService
{
    /**
     * @return array{path: string, total_filas: int}
     */
    public function normalizarArchivo(ImportacionAlmacenLog $log): array
    {
        $sourcePath = Storage::path($log->archivo_ruta);
        if (! file_exists($sourcePath)) {
            throw new \RuntimeException('Archivo de importación no encontrado.');
        }

        $directorio = dirname($log->archivo_ruta);
        $destinoRelativo = $directorio . '/normalizado.csv';
        $destinoAbsoluto = Storage::path($destinoRelativo);

        if (! is_dir(dirname($destinoAbsoluto))) {
            mkdir(dirname($destinoAbsoluto), 0755, true);
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'txt'], true)) {
            $this->copiarCsvNormalizado($sourcePath, $destinoAbsoluto);
        } else {
            $this->convertirExcelACsv($sourcePath, $destinoAbsoluto);
        }

        $totalFilas = $this->contarFilasDatos($destinoAbsoluto);

        return [
            'path' => $destinoRelativo,
            'total_filas' => $totalFilas,
        ];
    }

    /**
     * @return array{filas: list<array{numero_fila: int, row: array<string, mixed>}>, headers: list<string>}
     */
    public function leerLote(ImportacionAlmacenLog $log, int $offset, int $limite): array
    {
        $csvPath = $log->archivo_normalizado;
        if (! $csvPath || ! Storage::exists($csvPath)) {
            throw new \RuntimeException('Archivo normalizado no disponible.');
        }

        $fullPath = Storage::path($csvPath);
        $file = fopen($fullPath, 'r');
        if ($file === false) {
            throw new \RuntimeException('No se pudo abrir el archivo normalizado.');
        }

        $headers = $this->leerCabecerasCsv($file);
        $filas = [];
        $indiceDatos = 0;
        $numeroFila = 1;

        while (($row = fgetcsv($file)) !== false) {
            $numeroFila++;

            if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            if ($indiceDatos < $offset) {
                $indiceDatos++;
                continue;
            }

            if (count($filas) >= $limite) {
                break;
            }

            $rowNormalizado = $this->alinearFila($row, $headers);
            $asociativo = array_combine($headers, $rowNormalizado);
            if ($asociativo === false) {
                fclose($file);
                throw new \RuntimeException('Error al leer filas del archivo.');
            }

            $filas[] = [
                'numero_fila' => $numeroFila,
                'row' => $asociativo,
            ];
            $indiceDatos++;
        }

        fclose($file);

        return [
            'filas' => $filas,
            'headers' => $headers,
        ];
    }

    private function copiarCsvNormalizado(string $origen, string $destino): void
    {
        $in = fopen($origen, 'r');
        $out = fopen($destino, 'w');
        if ($in === false || $out === false) {
            throw new \RuntimeException('No se pudo normalizar el archivo CSV.');
        }

        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        while (($row = fgetcsv($in)) !== false) {
            fputcsv($out, $row);
        }

        fclose($in);
        fclose($out);
    }

    private function convertirExcelACsv(string $origen, string $destino): void
    {
        $rows = (new FastExcel)->import($origen);
        $out = fopen($destino, 'w');
        if ($out === false) {
            throw new \RuntimeException('No se pudo crear el CSV normalizado.');
        }

        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headersEscritos = false;
        foreach ($rows as $row) {
            if (! $headersEscritos) {
                $headers = array_keys($row);
                fputcsv($out, $headers);
                $headersEscritos = true;
            }
            fputcsv($out, array_values($row));
        }

        if (! $headersEscritos) {
            fclose($out);
            throw new \RuntimeException('El archivo no contiene filas para importar.');
        }

        fclose($out);
    }

    private function contarFilasDatos(string $csvPath): int
    {
        $file = fopen($csvPath, 'r');
        if ($file === false) {
            return 0;
        }

        $this->leerCabecerasCsv($file);
        $total = 0;

        while (($row = fgetcsv($file)) !== false) {
            if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }
            $total++;
        }

        fclose($file);

        return $total;
    }

    /**
     * @return list<string>
     */
    private function leerCabecerasCsv($file): array
    {
        $headers = fgetcsv($file);
        if ($headers === false) {
            throw new \RuntimeException('El archivo no tiene cabeceras.');
        }

        return array_map(
            fn ($h) => trim((string) $h),
            $headers,
        );
    }

    /**
     * @param  list<string|null>  $row
     * @param  list<string>  $headers
     * @return list<string>
     */
    private function alinearFila(array $row, array $headers): array
    {
        $alineada = [];
        foreach ($headers as $i => $header) {
            $alineada[] = isset($row[$i]) ? (string) $row[$i] : '';
        }

        return $alineada;
    }
}
