<?php

namespace App\Services\Tiendanube\Precios\Exportacion;

use App\Exceptions\Tiendanube\TiendanubePrecioCsvException;

class TiendanubePrecioCsvEscritor
{
    public const LIMITE_LINEAS = 20000;

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string>>  $filas
     * @return array{path: string, hash: string, filas: int, particiones: int}
     */
    public function escribir(string $rutaAbsoluta, array $encabezados, array $filas): array
    {
        $dir = dirname($rutaAbsoluta);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new TiendanubePrecioCsvException('No se pudo crear el directorio de exportación.', 'archivo');
        }

        $limiteDatos = self::LIMITE_LINEAS - 1;
        if (count($filas) > $limiteDatos) {
            throw new TiendanubePrecioCsvException(
                'La exportación supera el límite de 20,000 líneas del importador. Reduzca el lote.',
                'limite_filas'
            );
        }

        $out = fopen($rutaAbsoluta, 'w');
        if ($out === false) {
            throw new TiendanubePrecioCsvException('No se pudo crear el archivo CSV.', 'archivo');
        }

        fputcsv($out, $encabezados);
        foreach ($filas as $fila) {
            fputcsv($out, $fila);
        }
        fclose($out);

        return [
            'path' => $rutaAbsoluta,
            'hash' => hash_file('sha256', $rutaAbsoluta) ?: '',
            'filas' => count($filas),
            'particiones' => 1,
        ];
    }
}
