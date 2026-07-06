<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\User;
use App\Support\RhReciboAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Carbon\Carbon;

class GenerarReciboIncidenciaService
{
    public function individual(RhDeduccion $deduccion): DomPdfInstance
    {
        $deduccion->load([
            'colaborador.departamento',
            'colaborador.area',
            'colaborador.puesto',
            'reglaIncidencia',
            'registradoPor',
        ]);

        $deptoNombre = $deduccion->departamento_snapshot
            ?? $deduccion->colaborador?->departamento?->nombre;

        return Pdf::loadView('rh.recibo_incidencia', [
            'deduccion' => $deduccion,
            'colaborador' => $deduccion->colaborador,
            'fecha' => $deduccion->fecha_ocurrencia?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'encabezado' => RhReciboAssets::encabezadoParaDepartamento($deptoNombre),
        ])->setPaper('letter', 'portrait');
    }
}
