<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\DocumentoVentaRevision;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoImportacion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use Illuminate\Support\Facades\DB;

class ReiniciarDocumentosPeriodoEscalonamiento
{
    /**
     * Elimina documentos, movimientos, resúmenes e importaciones del período para permitir una carga limpia.
     *
     * @return array<string, int>
     */
    public function reiniciar(EscalonamientoPeriodo $periodo): array
    {
        if ($periodo->estaCerradoOficialmente()) {
            throw new \InvalidArgumentException('El período ya tiene cierre aplicado y no admite reinicio de documentos.');
        }

        return DB::transaction(function () use ($periodo) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);
            $documentoIds = DocumentoVenta::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->pluck('id');

            $conteo = [
                'documentos' => $documentoIds->count(),
                'movimientos' => 0,
                'incidencias' => 0,
                'importaciones' => 0,
                'resumenes' => 0,
            ];

            if ($documentoIds->isNotEmpty()) {
                EscalonamientoAplicacionDevolucion::query()
                    ->where(function ($q) use ($documentoIds) {
                        $q->whereIn('documento_devolucion_id', $documentoIds)
                            ->orWhereIn('documento_venta_original_id', $documentoIds)
                            ->orWhereIn('documento_remision_vinculada_id', $documentoIds);
                    })
                    ->delete();

                $conteo['movimientos'] = EscalonamientoMovimiento::query()
                    ->where('escalonamiento_periodo_id', $periodo->id)
                    ->delete();

                DocumentoVentaRevision::query()
                    ->whereIn('documento_venta_id', $documentoIds)
                    ->delete();

                EscalonamientoIncidencia::query()
                    ->where('escalonamiento_periodo_id', $periodo->id)
                    ->delete();

                DocumentoVenta::query()
                    ->whereIn('id', $documentoIds)
                    ->delete();
            } else {
                EscalonamientoIncidencia::query()
                    ->where('escalonamiento_periodo_id', $periodo->id)
                    ->delete();
            }

            $importacionIds = EscalonamientoImportacion::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->pluck('id');

            if ($importacionIds->isNotEmpty()) {
                DB::table('escalonamiento_importacion_filas')
                    ->whereIn('escalonamiento_importacion_id', $importacionIds)
                    ->delete();
                $conteo['importaciones'] = EscalonamientoImportacion::query()
                    ->whereIn('id', $importacionIds)
                    ->delete();
            }

            $conteo['resumenes'] = EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->delete();

            $periodo->fecha_corte = null;
            $periodo->save();

            return $conteo;
        });
    }
}
