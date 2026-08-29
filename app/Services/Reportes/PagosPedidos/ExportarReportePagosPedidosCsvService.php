<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarReportePagosPedidosCsvService
{
    public function __construct(
        private ListarReportePagosPedidosService $listar,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function resumen(User $usuario, array $params): StreamedResponse
    {
        $filas = $this->filasResumen($this->listar->cierresFiltrados($usuario, $params));

        return $this->stream($filas, 'pagos_pedidos_resumen_'.now()->format('Ymd_His').'.csv');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function detalle(User $usuario, array $params): StreamedResponse
    {
        return $this->stream(
            $this->filasDetalle($this->listar->cierresFiltrados($usuario, $params)),
            'pagos_pedidos_detalle_'.now()->format('Ymd_His').'.csv'
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{nombre_archivo: string, path: string, tamano_bytes: int, num_registros: int}
     */
    public function guardarResumen(User $usuario, array $params): array
    {
        return $this->guardarArchivo(
            $this->filasResumen($this->listar->cierresFiltrados($usuario, $params)),
            'pagos_pedidos_resumen_'.now()->format('Ymd_His').'.csv'
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{nombre_archivo: string, path: string, tamano_bytes: int, num_registros: int}
     */
    public function guardarDetalle(User $usuario, array $params): array
    {
        return $this->guardarArchivo(
            $this->filasDetalle($this->listar->cierresFiltrados($usuario, $params)),
            'pagos_pedidos_detalle_'.now()->format('Ymd_His').'.csv'
        );
    }

    /** @param  Collection<int, PedidoBmaCierrePago>  $cierres */
    private function filasResumen(Collection $cierres): Collection
    {
        return $cierres->map(function (PedidoBmaCierrePago $c) {
            $items = $c->items;
            $vigentes = $items->where('activo_para_cobertura_snapshot', true);
            $historicos = $items->where('activo_para_cobertura_snapshot', false);
            $bancos = $items->pluck('banco_snapshot')->filter()->unique()->implode('; ');
            $formas = $items->pluck('forma_pago_snapshot')->filter()->unique()
                ->map(fn ($f) => PedidoBmaPago::labelForma($f) ?? $f)->implode('; ');

            return [
                'Fecha validación' => $c->validado_at?->format('Y-m-d'),
                'Hora validación' => $c->validado_at?->format('H:i:s'),
                'Versión cierre' => $c->version,
                'Estado cierre' => $c->estado,
                'Folio GELIA' => $c->folio_snapshot,
                'Folio remisión' => $c->folio_remision_snapshot,
                'Fecha pedido' => $c->pedido_fecha?->format('Y-m-d'),
                'Número cliente' => $c->cliente?->numero_cliente,
                'Cliente' => $c->cliente?->nombre,
                'Atendió' => $c->vendedor?->name,
                'Departamento' => $c->departamento?->nombre,
                'Almacén' => $c->almacen?->nombre,
                'Origen/modalidad' => $c->metadata_snapshot['origen'] ?? '',
                'Monto venta' => $c->monto_venta,
                'Envío' => $c->monto_envio,
                'Seguro' => $c->monto_seguro,
                'Total pedido' => $c->total_pedido,
                'SAF aplicado' => $c->saf_aplicado,
                'Total a cobrar' => $c->total_a_cobrar,
                'Pagos válidos' => $c->pagos_validos,
                'Diferencia' => $c->diferencia,
                'Excedente' => $c->excedente,
                'Tolerancia aplicada' => $c->tolerancia_aplicada,
                'Estado cobertura' => $c->estado_cobertura,
                'Exhibiciones vigentes' => $vigentes->count(),
                'Exhibiciones históricas' => $historicos->count(),
                'Bancos' => $bancos,
                'Formas de pago' => $formas,
                'Cantidad vouchers' => $items->whereNotNull('ruta_archivo_snapshot')->count(),
                'Remisión disponible' => ! empty($c->metadata_snapshot['remision_documento_id']) ? 'Sí' : 'No',
                'Validó' => $c->validadoPor?->name,
            ];
        });
    }

    /** @param  Collection<int, PedidoBmaCierrePago>  $cierres */
    private function filasDetalle(Collection $cierres): Collection
    {
        $filas = collect();
        foreach ($cierres as $c) {
            foreach ($c->items as $item) {
                $filas->push([
                    'Fecha validación' => $c->validado_at?->format('Y-m-d H:i:s'),
                    'Folio GELIA' => $c->folio_snapshot,
                    'Folio remisión' => $c->folio_remision_snapshot,
                    'Cliente' => $c->cliente?->nombre,
                    'Atendió' => $c->vendedor?->name,
                    'ID exhibición' => $item->pedido_bma_pago_id,
                    'Número exhibición' => $item->numero_exhibicion,
                    'Monto' => $item->monto_snapshot,
                    'Forma de pago' => PedidoBmaPago::labelForma($item->forma_pago_snapshot),
                    'Banco' => $item->banco_snapshot,
                    'Referencia' => $item->referencia_snapshot,
                    'Fecha pago' => $item->fecha_pago_snapshot?->format('Y-m-d'),
                    'Registró' => $item->capturadoPor?->name,
                    'Registrado en' => $item->capturado_at_snapshot?->format('Y-m-d H:i:s'),
                    'Estado revisión' => $item->estado_revision_snapshot,
                    'Revisó' => $item->revisadoPor?->name,
                    'Revisado en' => $item->revisado_at_snapshot?->format('Y-m-d H:i:s'),
                    'Cuenta cobertura' => $item->activo_para_cobertura_snapshot ? 'Sí' : 'No',
                    'Motivo rechazo' => $item->motivo_rechazo_snapshot,
                    'Pago sustituido ID' => $item->reemplaza_pago_id,
                    'Nombre voucher' => $item->nombre_archivo_snapshot,
                    'MIME' => $item->mime_type_snapshot,
                    'Evidencia ID' => $item->pedido_bma_pago_id,
                ]);
            }
        }

        return $filas;
    }

    /** @return array{nombre_archivo: string, path: string, tamano_bytes: int, num_registros: int} */
    private function guardarArchivo(Collection $filas, string $nombreArchivo): array
    {
        Storage::disk('local')->makeDirectory('reportes_pagos_pedidos');
        $path = 'reportes_pagos_pedidos/'.$nombreArchivo;
        $abs = Storage::disk('local')->path($path);

        $out = fopen($abs, 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        if ($filas->isEmpty()) {
            fputcsv($out, ['Sin registros']);
            $registros = 0;
        } else {
            fputcsv($out, array_keys($filas->first()));
            foreach ($filas as $fila) {
                fputcsv($out, array_values($fila));
            }
            $registros = $filas->count();
        }
        fclose($out);

        return [
            'nombre_archivo' => $nombreArchivo,
            'path' => $path,
            'tamano_bytes' => (int) filesize($abs),
            'num_registros' => $registros,
        ];
    }

    private function stream(Collection $filas, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            if ($filas->isEmpty()) {
                fputcsv($out, ['Sin registros']);
            } else {
                fputcsv($out, array_keys($filas->first()));
                foreach ($filas as $fila) {
                    fputcsv($out, array_values($fila));
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
