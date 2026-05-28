<?php

namespace App\Services\Solicitudes;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportarFacturasSolicitudService
{
    private const COLUMNAS_REQUERIDAS = [
        'RFC' => 'rfc',
        'CODIGO POSTAL' => 'codigo_postal',
        'REGIMEN FISCAL' => 'regimen_fiscal',
        'CORREO ELECTRONICO' => 'correo_electronico',
        'USO DE FACTURA' => 'uso_factura',
        'NOMBRE (RAZON SOCIAL)' => 'nombre_razon_social',
    ];

    public function etiquetasParaUi(): array
    {
        return [
            'rfc' => 'RFC',
            'codigo_postal' => 'Código Postal',
            'regimen_fiscal' => 'Régimen Fiscal',
            'correo_electronico' => 'Correo Electrónico',
            'uso_factura' => 'Uso de Factura',
            'nombre_razon_social' => 'Nombre (Razón Social)',
        ];
    }

    public function validar(UploadedFile $archivo): void
    {
        $this->extraer($archivo);
    }

    /**
     * @return array{rfc: string, codigo_postal: string, regimen_fiscal: string, correo_electronico: string, uso_factura: string, nombre_razon_social: string}
     */
    public function extraer(UploadedFile $archivo): array
    {
        set_time_limit(30);

        $extension = strtolower($archivo->getClientOriginalExtension());
        $path = $archivo->getRealPath();

        if (!$path || !is_readable($path)) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'No se pudo leer el archivo subido.',
            ]);
        }

        return $this->extraerDesdeRuta($path, $extension);
    }

    /**
     * @return array{rfc: string, codigo_postal: string, regimen_fiscal: string, correo_electronico: string, uso_factura: string, nombre_razon_social: string}
     */
    public function extraerDesdeRuta(string $path, string $extension): array
    {
        set_time_limit(30);

        $extension = strtolower($extension);
        $filas = $extension === 'csv'
            ? $this->obtenerFilasDesdeCsv($path)
            : $this->obtenerFilasDesdeExcel($path);

        $this->validarFilas($filas);

        return $filas[0];
    }

    public function descargarPlantilla(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filas = [
            [
                'RFC' => 'XAXX010101000',
                'CODIGO POSTAL' => '12345',
                'REGIMEN FISCAL' => '601',
                'CORREO ELECTRONICO' => 'facturacion@ejemplo.com',
                'USO DE FACTURA' => 'G03',
                'NOMBRE (RAZON SOCIAL)' => 'EMPRESA EJEMPLO SA DE CV',
            ],
        ];

        return (new FastExcel($filas))->download('plantilla-datos-fiscales-factura.xlsx');
    }

    private function obtenerFilasDesdeCsv(string $path): array
    {
        $file = fopen($path, 'r');
        if ($file === false) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'No se pudo leer el archivo CSV.',
            ]);
        }

        $headersRaw = fgetcsv($file);
        if ($headersRaw === false) {
            fclose($file);
            throw ValidationException::withMessages([
                'archivo_facturas' => 'El archivo CSV está vacío.',
            ]);
        }

        $mapa = $this->mapearCabeceras($headersRaw);
        $filas = [];

        while ($row = fgetcsv($file)) {
            if ($this->filaVacia($row)) {
                continue;
            }
            $filas[] = $this->extraerFila($row, $mapa);
        }

        fclose($file);

        return $filas;
    }

    private function obtenerFilasDesdeExcel(string $path): array
    {
        if (!is_readable($path)) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'No se pudo leer el archivo Excel.',
            ]);
        }

        $filas = [];
        $filasEscaneadas = 0;
        $cabecerasValidadas = false;

        try {
            foreach ((new FastExcel)->import($path) as $row) {
                $filasEscaneadas++;
                $rowArray = is_array($row) ? $row : (array) $row;

                if (!$cabecerasValidadas) {
                    $this->validarCabecerasDeClaves(array_keys($rowArray));
                    $cabecerasValidadas = true;
                }

                if ($filasEscaneadas > 2) {
                    throw ValidationException::withMessages([
                        'archivo_facturas' => 'El archivo solo puede contener una fila de datos fiscales. Descargue la plantilla oficial.',
                    ]);
                }

                $mapeada = $this->mapearFilaAsociativa($rowArray);
                if ($mapeada === null) {
                    continue;
                }
                if ($this->filaVacia(array_values($mapeada))) {
                    continue;
                }
                $filas[] = $mapeada;
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'No se pudo procesar el archivo Excel. Descargue la plantilla oficial y verifique el formato.',
            ]);
        }

        if (!$cabecerasValidadas) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'El archivo Excel está vacío. Descargue la plantilla oficial y agregue una fila de datos fiscales.',
            ]);
        }

        return $filas;
    }

    private function validarCabecerasDeClaves(array $claves): void
    {
        $mapa = [];
        foreach ($claves as $clave) {
            $normalizada = $this->normalizarCabecera(str_replace('_', ' ', (string) $clave));
            $mapa[$normalizada] = 0;
        }

        $this->validarCabecerasPresentes($mapa);
    }

    private function mapearFilaAsociativa(array $row): ?array
    {
        $normalizado = [];
        foreach ($row as $key => $value) {
            $header = $this->normalizarCabecera(str_replace('_', ' ', (string) $key));
            $normalizado[$header] = $this->stringifyCell($value);
        }

        $faltantes = [];
        foreach (array_keys(self::COLUMNAS_REQUERIDAS) as $columna) {
            if (!array_key_exists($columna, $normalizado)) {
                $faltantes[] = $columna;
            }
        }

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'Faltan columnas obligatorias: ' . implode(', ', $faltantes) . '. Descargue la plantilla oficial.',
            ]);
        }

        $datos = [];
        foreach (self::COLUMNAS_REQUERIDAS as $columna => $clave) {
            $datos[$clave] = $normalizado[$columna];
        }

        return $datos;
    }

    private function normalizarCabecera(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtoupper(trim($header));
        $header = preg_replace('/\s+/', ' ', $header);

        return match (true) {
            in_array($header, ['NOMBRE', 'RAZON SOCIAL', 'NOMBRE RAZON SOCIAL'], true) => 'NOMBRE (RAZON SOCIAL)',
            default => $header,
        };
    }

    private function mapearCabeceras(array $headersRaw): array
    {
        $mapa = [];
        foreach ($headersRaw as $index => $header) {
            $normalizada = $this->normalizarCabecera((string) $header);
            $mapa[$normalizada] = $index;
        }

        return $mapa;
    }

    private function validarCabecerasPresentes(array $mapa): void
    {
        $faltantes = [];
        foreach (array_keys(self::COLUMNAS_REQUERIDAS) as $columna) {
            if (!array_key_exists($columna, $mapa)) {
                $faltantes[] = $columna;
            }
        }

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'Faltan columnas obligatorias: ' . implode(', ', $faltantes) . '. Descargue la plantilla oficial.',
            ]);
        }
    }

    private function extraerFila(array $row, array $mapa): array
    {
        $this->validarCabecerasPresentes($mapa);

        $datos = [];
        foreach (self::COLUMNAS_REQUERIDAS as $columna => $clave) {
            $index = $mapa[$columna];
            $datos[$clave] = trim((string) ($row[$index] ?? ''));
        }

        return $datos;
    }

    private function validarFilas(array $filas): void
    {
        if (count($filas) === 0) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'El archivo debe contener exactamente una fila de datos fiscales. Descargue la plantilla oficial.',
            ]);
        }

        if (count($filas) > 1) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'El archivo solo puede contener una fila de datos fiscales. Descargue la plantilla oficial.',
            ]);
        }

        $fila = $filas[0];

        foreach (self::COLUMNAS_REQUERIDAS as $columna => $clave) {
            if ($fila[$clave] === '') {
                throw ValidationException::withMessages([
                    'archivo_facturas' => "La columna {$columna} es obligatoria y no puede estar vacía.",
                ]);
            }
        }

        $rfc = strtoupper(preg_replace('/\s+/', '', $fila['rfc']));
        if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'El RFC en el Excel no tiene un formato válido.',
            ]);
        }

        if (!preg_match('/^\d{5}$/', $fila['codigo_postal'])) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'El código postal debe tener 5 dígitos.',
            ]);
        }

        if (!filter_var($fila['correo_electronico'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'archivo_facturas' => 'El correo electrónico en el Excel no es válido.',
            ]);
        }
    }

    private function filaVacia(array $row): bool
    {
        return empty(array_filter($row, fn ($v) => $this->stringifyCell($v) !== ''));
    }

    private function stringifyCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_array($value)) {
            return trim(implode(' ', array_map(fn ($v) => $this->stringifyCell($v), $value)));
        }

        return trim((string) $value);
    }
}
