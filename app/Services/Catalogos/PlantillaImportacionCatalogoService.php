<?php

namespace App\Services\Catalogos;

use Symfony\Component\HttpFoundation\StreamedResponse;

class PlantillaImportacionCatalogoService
{
    private const PLANTILLAS = [
        'sucursales' => [
            'filename' => 'plantilla_sucursales.csv',
            'headers' => ['codigo', 'nombre', 'activo'],
            'ejemplo' => ['SUC01', 'Matriz CDMX', '1'],
        ],
        'tipos_almacen' => [
            'filename' => 'plantilla_tipos_almacen.csv',
            'headers' => ['nombre'],
            'ejemplo' => ['Piso de venta'],
        ],
        'marcas_producto' => [
            'filename' => 'plantilla_marcas_producto.csv',
            'headers' => ['nombre', 'activo'],
            'ejemplo' => ['Marca Ejemplo', '1'],
        ],
        'categorias_producto' => [
            'filename' => 'plantilla_categorias_producto.csv',
            'headers' => ['nombre'],
            'ejemplo' => ['Aromas'],
        ],
        'almacenes' => [
            'filename' => 'plantilla_almacenes.csv',
            'headers' => ['codigo', 'nombre', 'sucursal_codigo', 'tipo_almacen', 'activo'],
            'ejemplo' => ['ALM01', 'Bodega Norte', 'SUC01', 'Bodega', '1'],
        ],
        'productos' => [
            'filename' => 'plantilla_productos.csv',
            'headers' => ['folio', 'sku', 'descripcion', 'marca', 'categoria', 'codigo_barras', 'peso', 'activo'],
            'ejemplo' => ['100125', '12345', 'Producto demo', 'Marca X', 'Categoria Y', '7501234567890', '0.5', '1'],
        ],
        'inventarios' => [
            'filename' => 'plantilla_inventarios.csv',
            'headers' => ['folio', 'sku', 'descripcion', 'categoria', 'marca', 'existencia', 'costo', 'costo_reposicion', 'precio_venta'],
            'ejemplo' => ['100125', '12345', 'Producto demo', 'Aromas', 'Marca X', '100', '50.00', '55.00', '89.99'],
        ],
    ];

    public function tiposValidos(): array
    {
        return array_keys(self::PLANTILLAS);
    }

    public function descargar(string $tipo): StreamedResponse
    {
        if (! isset(self::PLANTILLAS[$tipo])) {
            abort(404, 'Plantilla no encontrada.');
        }

        $plantilla = self::PLANTILLAS[$tipo];
        $headers = $plantilla['headers'];
        $ejemplo = $plantilla['ejemplo'];

        return response()->streamDownload(function () use ($headers, $ejemplo) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, $headers);
            fputcsv($out, $ejemplo);
            fclose($out);
        }, $plantilla['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
