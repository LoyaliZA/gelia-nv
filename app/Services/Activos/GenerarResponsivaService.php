<?php

namespace App\Services\Activos;

use App\Models\ActivoAsignacion;
use App\Models\ActivoConfiguracion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Support\Collection;

class GenerarResponsivaService
{
    public function individual(ActivoAsignacion $asignacion): DomPdfInstance
    {
        $asignacion->load([
            'activo.tipo',
            'activo.departamento',
            'activo.categoria',
            'activo.accesorios.tipo',
            'usuario.area.departamento',
            'usuario.departamentos',
            'usuario.areas',
            'asignadoPor',
        ]);

        return Pdf::loadView('activos.responsiva', [
            'asignacion' => $asignacion,
            'activo' => $asignacion->activo,
            'usuario' => $asignacion->usuario,
            'fecha' => $asignacion->fecha_inicio
                ? $asignacion->fecha_inicio->format('d/m/Y')
                : now()->format('d/m/Y'),
            'terminos' => ActivoConfiguracion::obtenerTerminos(),
        ]);
    }

    public function conjunta(User $usuario, Collection $asignaciones): DomPdfInstance
    {
        $usuario->loadMissing(['area.departamento', 'departamentos', 'areas']);

        return Pdf::loadView('activos.responsiva_conjunta', [
            'asignaciones' => $asignaciones,
            'usuario' => $usuario,
            'fecha' => now()->format('d/m/Y'),
            'terminos' => ActivoConfiguracion::obtenerTerminos(),
        ]);
    }
}
