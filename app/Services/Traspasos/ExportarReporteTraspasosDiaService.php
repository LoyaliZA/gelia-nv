<?php

namespace App\Services\Traspasos;

use App\Models\SolicitudTraspaso;
use App\Models\User;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarReporteTraspasosDiaService
{
    public function __construct(
        private ListarSolicitudesTraspasoService $listar
    ) {}

    public function consulta(?User $usuario, string $fechaEntrega): Collection
    {
        return $this->listar->ejecutar($usuario, [
            'fecha_entrega' => $fechaEntrega,
        ], false);
    }

    public function exportar(?User $usuario, string $fechaEntrega, string $formato = 'xlsx')
    {
        $filas = $this->consulta($usuario, $fechaEntrega)->map(function (SolicitudTraspaso $t) {
            return [
                'Folio' => $t->folio,
                'Folio traspaso' => $t->folio_traspaso ?? '',
                'Estado' => $t->estado->nombre ?? '',
                'Cliente' => $t->cliente->nombre ?? '',
                'No. cliente' => $t->cliente->numero_cliente ?? '',
                'Vendedor' => $t->vendedor->name ?? '',
                'Almacén origen' => $t->almacenOrigen->nombre ?? '',
                'Total piezas' => $t->total_piezas,
                'Horario' => $t->horario->nombre ?? '',
                'Fecha entrega estimada' => optional($t->fecha_entrega_estimada)?->format('Y-m-d') ?? '',
                'Solicitado' => optional($t->created_at)?->format('Y-m-d H:i') ?? '',
                'Productos' => $t->productos->map(fn ($p) => "{$p->sku} x{$p->piezas}")->implode('; '),
            ];
        });

        $nombre = 'traspasos_planificados_' . $fechaEntrega;

        if ($formato === 'csv') {
            return $this->streamCsv($filas, $nombre . '.csv');
        }

        return (new FastExcel($filas))->download($nombre . '.xlsx');
    }

    private function streamCsv(Collection $filas, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            if ($filas->isEmpty()) {
                fputcsv($out, ['Sin registros']);
            } else {
                fputcsv($out, array_keys($filas->first()));
                foreach ($filas as $fila) {
                    fputcsv($out, array_values($fila));
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
