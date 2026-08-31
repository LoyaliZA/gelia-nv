<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Support\Reportes\ExhibicionVouchersValidadosMapper;
use App\Support\Reportes\FechasPagoReporte;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarReporteVouchersValidadosCsvService
{
    public function __construct(
        private AplicarFiltrosReporteVouchersValidadosQuery $filtros,
        private ExhibicionVouchersValidadosMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function ejecutar(User $usuario, array $params): StreamedResponse
    {
        return $this->stream(
            $this->filas($usuario, $params),
            'vouchers_validados_'.now()->format('Ymd_His').'.csv'
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{nombre_archivo: string, path: string, tamano_bytes: int, num_registros: int}
     */
    public function guardar(User $usuario, array $params): array
    {
        return $this->guardarArchivo(
            $this->filas($usuario, $params),
            'vouchers_validados_'.now()->format('Ymd_His').'.csv'
        );
    }

    /** @param  array<string, mixed>  $params */
    private function filas(User $usuario, array $params): Collection
    {
        $duplicados = $this->filtros->posiblesDuplicados($usuario, $params);
        $items = $this->filtros->itemsVisibles($usuario, $params);

        if (! empty($params['posible_duplicado'])) {
            $items = array_values(array_filter(
                $items,
                fn (PedidoBmaCierrePagoItem $i) => isset($duplicados[$i->id])
            ));
        }

        return collect($items)->map(function (PedidoBmaCierrePagoItem $item) use ($usuario, $duplicados) {
            $cierre = $item->cierre;
            if (! $cierre) {
                return null;
            }

            $fila = $this->mapper->fila($item, $cierre, $usuario, $duplicados);
            $saf = (float) ($cierre->saf_aplicado ?? 0);

            return [
                'Pedido' => $fila['folio_pedido'],
                'Remisión' => $fila['folio_remision'],
                'Cliente' => $fila['cliente']['nombre'] ?? null,
                'Número cliente' => $fila['cliente']['numero_cliente'] ?? null,
                'Número exhibición' => $fila['numero_exhibicion'],
                'Monto' => (float) $fila['monto'],
                'Forma de pago' => $fila['forma_pago_label'],
                'Banco' => $fila['banco'],
                'Referencia' => $fila['referencia'],
                'Fecha movimiento' => $fila['fecha_pago'] ? FechasPagoReporte::formatear(\Illuminate\Support\Carbon::parse($fila['fecha_pago'])) : FechasPagoReporte::SIN_INFORMACION,
                'Fecha reporte' => $fila['capturado_at_label'],
                'Fecha validación' => $fila['validado_at_label'],
                'Estado' => $fila['estado_validacion_label'],
                'Reportado por' => $fila['reportado_por'],
                'Validado por' => $fila['validado_por'],
                'Reportado posteriormente' => $fila['reportado_posteriormente'] ? 'Sí' : 'No',
                'SAF relacionado' => $saf > 0.005 ? number_format($saf, 2, '.', '') : '',
                'Observaciones' => $fila['observaciones'],
                'Referencia voucher' => $fila['evidencia']['nombre'] ?? $item->ruta_archivo_snapshot,
            ];
        })->filter()->values();
    }

    /** @param  Collection<int, array<string, mixed>>  $filas */
    private function stream(Collection $filas, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            if ($filas->isEmpty()) {
                fputcsv($out, ['Sin registros']);
                fclose($out);

                return;
            }
            $headers = array_keys($filas->first());
            fputcsv($out, $headers);
            foreach ($filas as $fila) {
                fputcsv($out, array_values($fila));
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{nombre_archivo: string, path: string, tamano_bytes: int, num_registros: int}
     */
    private function guardarArchivo(Collection $filas, string $nombre): array
    {
        Storage::disk('local')->makeDirectory('reportes_pagos_pedidos');
        $path = 'reportes_pagos_pedidos/'.$nombre;
        $handle = fopen('php://temp', 'r+');
        if ($filas->isNotEmpty()) {
            fputcsv($handle, array_keys($filas->first()));
            foreach ($filas as $fila) {
                fputcsv($handle, array_values($fila));
            }
        } else {
            fputcsv($handle, ['Sin registros']);
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);
        Storage::disk('local')->put($path, $contents);

        return [
            'nombre_archivo' => $nombre,
            'path' => $path,
            'tamano_bytes' => strlen($contents),
            'num_registros' => $filas->count(),
        ];
    }
}
