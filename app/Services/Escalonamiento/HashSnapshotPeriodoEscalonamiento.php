<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoImportacion;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;

class HashSnapshotPeriodoEscalonamiento
{
    public function calcular(EscalonamientoPeriodo $periodo): string
    {
        $documentos = DocumentoVenta::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->count();

        $sumaEfectos = (string) EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->sum('efecto');

        $ultimaImportacion = EscalonamientoImportacion::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->max('updated_at');

        $payload = [
            'periodo_id' => $periodo->id,
            'regla_version_id' => $periodo->escalonamiento_regla_version_id,
            'documentos' => $documentos,
            'suma_efectos' => bcadd($sumaEfectos, '0', 2),
            'ultima_importacion' => $ultimaImportacion,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
