<?php

namespace App\Services\Tiendanube\Precios\Exportacion;

use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteAprobacionService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use Illuminate\Support\Facades\Storage;

class ExportarReporteRevisionCsvService
{
    public function __construct(
        private readonly TiendanubePrecioLoteAprobacionService $aprobacion,
        private readonly TiendanubePrecioLoteService $lotes,
        private readonly TiendanubePrecioCsvEscritor $escritor,
    ) {}

    /**
     * @return array{path: string, nombre: string}
     */
    public function generar(string $loteId, int $storeId, int $userId): array
    {
        $this->lotes->obtenerAutorizado($loteId, $storeId, $userId);
        $revision = $this->aprobacion->obtenerRevisionAprobada($loteId, $storeId, $userId);
        $encabezados = [
            'NO_IMPORTABLE',
            'producto_id',
            'variante_id',
            'sku',
            'nombre',
            'excluido',
            'publicable',
            'intencion_normal',
            'valor_normal',
            'intencion_promocional',
            'valor_promocional',
            'intencion_costo',
            'valor_costo',
            'regla_normal',
            'regla_promo',
            'regla_costo',
        ];
        $filas = [];
        foreach ($revision['items'] as $item) {
            $campos = $item['campos'] ?? [];
            $filas[] = [
                'REPORTE INTERNO — NO IMPORTAR EN TIENDANUBE',
                (string) $item['producto_id'],
                (string) $item['variante_id'],
                (string) ($item['sku'] ?? ''),
                (string) ($item['nombre'] ?? ''),
                ! empty($item['excluido']) ? '1' : '0',
                ! empty($item['publicable']) ? '1' : '0',
                (string) ($campos['normal']['intencion'] ?? ''),
                (string) ($campos['normal']['valor_final'] ?? ''),
                (string) ($campos['promocional']['intencion'] ?? ''),
                (string) ($campos['promocional']['valor_final'] ?? ''),
                (string) ($campos['costo_remoto']['intencion'] ?? ''),
                (string) ($campos['costo_remoto']['valor_final'] ?? ''),
                (string) ($campos['normal']['regla_id'] ?? ''),
                (string) ($campos['promocional']['regla_id'] ?? ''),
                (string) ($campos['costo_remoto']['regla_id'] ?? ''),
            ];
        }

        $relativo = 'tiendanube/precio-csv/reportes/'.$loteId.'-rev'.$revision['revision_id'].'.csv';
        Storage::disk('local')->makeDirectory('tiendanube/precio-csv/reportes');
        $this->escritor->escribir(Storage::disk('local')->path($relativo), $encabezados, $filas);

        return [
            'path' => Storage::disk('local')->path($relativo),
            'nombre' => 'reporte-revision-'.$loteId.'-no-importable.csv',
        ];
    }
}
