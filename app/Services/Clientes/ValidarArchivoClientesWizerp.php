<?php

namespace App\Services\Clientes;

use Illuminate\Validation\ValidationException;

class ValidarArchivoClientesWizerp
{
    public static int $lecturas = 0;

    public function leer(string $path): array
    {
        self::$lecturas++;
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['archivo' => 'No se puede leer el archivo.']);
        }
        $file = fopen($path, 'r');
        if ($file === false) {
            throw ValidationException::withMessages(['archivo' => 'No se puede leer el archivo.']);
        }
        try {
            $raw = fgetcsv($file, escape: '');
            if (! is_array($raw)) {
                throw ValidationException::withMessages(['archivo' => 'El archivo está vacío.']);
            }
            $raw[0] = preg_replace('/^\xEF\xBB\xBF/', '', $raw[0] ?? '');
            $alias = [
                'numero_cli' => 'numero_cliente', 'monto_venta_actua' => 'monto_venta_actual',
                'limite_asignado' => 'monto_credito_autorizado', 'limite_de_credito' => 'monto_credito_autorizado',
                'dias_de_credito' => 'dias_credito', 'correo' => 'correo_electronico', 'email' => 'correo_electronico',
                'cp' => 'codigo_postal', 'razon_social' => 'nombre_razon_social',
            ];
            $headers = array_map(function ($v) use ($alias) {
                $texto = strtolower($this->texto((string) ($v ?? '')));

                return $alias[$texto] ?? $texto;
            }, $raw);
            if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers) || ! in_array('numero_cliente', $headers, true)) {
                throw ValidationException::withMessages(['archivo' => 'Cabeceras inválidas: se requiere numero_cliente y columnas únicas después de resolver alias.']);
            }
            $filas = [];
            $vistos = [];
            $numero = 1;
            while (($row = fgetcsv($file, escape: '')) !== false) {
                $numero++;
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    throw ValidationException::withMessages(['archivo' => "Fila {$numero}: número de columnas distinto de la cabecera."]);
                }
                $data = array_combine($headers, array_map(fn ($v) => $this->texto((string) ($v ?? '')), $row));
                $key = 'cliente:'.$data['numero_cliente'];
                $duplicada = isset($vistos[$key]) && $data['numero_cliente'] !== '';
                if ($duplicada && $vistos[$key]['data'] !== $data) {
                    throw ValidationException::withMessages(['archivo' => "Fila {$numero}: cliente duplicado contradictorio respecto a fila {$vistos[$key]['numero_fila']}."]);
                }
                $fila = ['data' => $data, 'numero_fila' => $numero, 'duplicada' => $duplicada];
                $filas[] = $fila;
                $vistos[$key] ??= $fila;
            }
            if ($filas === []) {
                throw ValidationException::withMessages(['archivo' => 'El archivo no contiene filas de datos.']);
            }

            return ['headers' => $headers, 'filas' => $filas];
        } finally {
            fclose($file);
        }
    }

    // Excel en Windows exporta CSV en Windows-1252; json_encode rechaza esos bytes.
    private function texto(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '' || preg_match('//u', $valor) === 1) {
            return $valor;
        }

        return mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
    }
}
