<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoCambioLista;
use App\Models\Escalonamiento\EscalonamientoCierre;
class RevisionCierrePrevioDiasUnoDos
{
    /**
     * @return null|array<string, mixed>
     */
    public function banner(): ?array
    {
        $dia = (int) now()->day;
        if ($dia > 2) {
            return null;
        }

        $ultimoCierre = EscalonamientoCierre::query()
            ->where('estado', EscalonamientoCierre::ESTADO_APLICADO)
            ->orderByDesc('aplicado_en')
            ->first();

        if (! $ultimoCierre) {
            return null;
        }

        $cambios = EscalonamientoCambioLista::query()
            ->where('escalonamiento_cierre_id', $ultimoCierre->id)
            ->count();

        $divergencias = 0;
        EscalonamientoCambioLista::query()
            ->where('escalonamiento_cierre_id', $ultimoCierre->id)
            ->with('cliente')
            ->chunkById(200, function ($filas) use (&$divergencias) {
                foreach ($filas as $fila) {
                    $operativa = $fila->cliente?->lista_actual_id;
                    if ($operativa && $fila->lista_nueva_id && (int) $operativa !== (int) $fila->lista_nueva_id) {
                        $divergencias++;
                    }
                }
            });

        $periodo = $ultimoCierre->periodo;

        return [
            'periodo' => $periodo ? sprintf('%04d-%02d', $periodo->anio, $periodo->mes) : null,
            'cierre_id' => $ultimoCierre->id,
            'cambios_registrados' => $cambios,
            'divergencias_operativa' => $divergencias,
            'aplicacion_externa_declarada' => (bool) $ultimoCierre->aplicacion_externa_declarada,
        ];
    }
}
