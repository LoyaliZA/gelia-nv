<?php

namespace App\Services\Productos;

use App\Models\Producto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportarProductosService
{
    public function __construct(
        private GenerarFolioProductoService $generarFolio,
    ) {}

    public function ejecutar(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        $file = fopen($path, 'r');

        if ($file === false) {
            return ['creados' => 0, 'actualizados' => 0, 'errores' => ['No se pudo leer el archivo.']];
        }

        $headers = $this->procesarCabeceras(fgetcsv($file) ?: []);
        $creados = 0;
        $actualizados = 0;
        $errores = [];
        $numeroFila = 1;

        DB::transaction(function () use ($file, $headers, &$creados, &$actualizados, &$errores, &$numeroFila) {
            while ($row = fgetcsv($file)) {
                $numeroFila++;

                if (empty(array_filter($row))) {
                    continue;
                }

                $rowNormalizado = $this->alinearFila($row, count($headers));
                $data = array_combine($headers, $rowNormalizado);

                if ($data === false) {
                    $errores[] = "Fila {$numeroFila}: columnas inválidas.";
                    continue;
                }

                $skuRaw = trim($data['sku'] ?? '');
                if ($skuRaw === '') {
                    $errores[] = "Fila {$numeroFila}: SKU vacío.";
                    continue;
                }

                $sku = Producto::normalizarSku($skuRaw);
                $descripcion = trim($data['descripcion'] ?? '') ?: $sku;
                $existencia = (int) ($data['existencia'] ?? 0);
                $costo = $this->parseDecimal($data['costo'] ?? '0');
                $precioVenta = isset($data['precio_venta']) && $data['precio_venta'] !== ''
                    ? $this->parseDecimal($data['precio_venta'])
                    : null;

                $producto = Producto::where('sku', $sku)->first();

                if ($producto) {
                    $producto->update([
                        'descripcion' => $descripcion,
                        'existencia' => $existencia,
                        'costo' => $costo,
                        'precio_venta' => $precioVenta,
                    ]);
                    $actualizados++;
                } else {
                    Producto::create([
                        'uuid' => (string) Str::uuid(),
                        'folio' => $this->generarFolio->ejecutar(),
                        'sku' => $sku,
                        'descripcion' => $descripcion,
                        'existencia' => $existencia,
                        'costo' => $costo,
                        'precio_venta' => $precioVenta,
                        'activo' => true,
                    ]);
                    $creados++;
                }
            }
        });

        fclose($file);

        return compact('creados', 'actualizados', 'errores');
    }

    private function procesarCabeceras(array $headers): array
    {
        return array_map(function ($header) {
            $normalizado = strtolower(trim((string) $header));
            $normalizado = str_replace([' ', '-'], '_', $normalizado);

            return match ($normalizado) {
                'precio', 'precio_venta', 'pg', 'precio_publico' => 'precio_venta',
                'costo', 'costo_wizerp', 'costo_producto' => 'costo',
                'existencia', 'stock', 'qty' => 'existencia',
                'descripcion', 'desc', 'nombre', 'producto' => 'descripcion',
                default => $normalizado,
            };
        }, $headers);
    }

    private function alinearFila(array $row, int $columnas): array
    {
        if (count($row) < $columnas) {
            return array_pad($row, $columnas, '');
        }

        return array_slice($row, 0, $columnas);
    }

    private function parseDecimal(mixed $valor): float
    {
        $limpio = str_replace(['$', ',', ' '], '', (string) $valor);

        return is_numeric($limpio) ? (float) $limpio : 0.0;
    }
}
