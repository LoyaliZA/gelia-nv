<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoCierreDetalle;
use Illuminate\Support\Facades\Storage;

class GenerarReporteAjustesErp
{
    public function generar(EscalonamientoCierre $cierre): string
    {
        $cierre->loadMissing('periodo');
        $periodo = $cierre->periodo;
        $detalles = EscalonamientoCierreDetalle::query()
            ->with(['cliente', 'listaSiguiente'])
            ->where('escalonamiento_cierre_id', $cierre->id)
            ->orderBy('cliente_id')
            ->get();

        $lineas = [];
        $lineas[] = implode(',', [
            'periodo',
            'version_cierre',
            'numero_cliente',
            'nombre_cliente',
            'lista_anterior_id',
            'lista_nueva_id',
            'compras',
            'devoluciones',
            'neto',
            'motivo',
            'bloqueo',
            'propone_inactivo',
            'meses_sin_compra',
        ]);

        foreach ($detalles as $detalle) {
            $lineas[] = implode(',', array_map(
                fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
                [
                    sprintf('%04d-%02d', $periodo->anio, $periodo->mes),
                    $cierre->version,
                    $detalle->cliente?->numero_cliente ?? '',
                    $detalle->cliente?->nombre ?? '',
                    $detalle->lista_operativa_id ?? '',
                    $detalle->lista_siguiente_id ?? '',
                    $detalle->compras,
                    $detalle->devoluciones,
                    $detalle->neto,
                    $detalle->motivo,
                    $detalle->cliente?->lista_bloqueada ? '1' : '0',
                    $detalle->propone_inactivo ? '1' : '0',
                    $detalle->meses_sin_compra,
                ],
            ));
        }

        $contenido = implode("\n", $lineas)."\n";
        $ruta = sprintf(
            'escalonamiento/cierres/cierre_%d_v%d.csv',
            $periodo->id,
            $cierre->version,
        );

        Storage::disk('local')->put($ruta, $contenido);

        $cierre->reporte_ruta = $ruta;
        $cierre->reporte_generado_en = now();
        $cierre->save();

        return $ruta;
    }
}
