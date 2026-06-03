<?php

namespace App\Services\Activos;

use App\Models\Activo;

class ConstruirSnapshotActivoService
{
    public function ejecutar(Activo $activo): array
    {
        $activo->loadMissing(['tipo', 'departamento', 'categoria']);

        return [
            'folio' => $activo->folio,
            'nombre' => $activo->nombre,
            'valor' => $activo->valor,
            'descripcion' => $activo->descripcion,
            'estado' => $activo->estado,
            'atributos' => $activo->atributos ?? [],
            'fecha_adquisicion' => $activo->fecha_adquisicion?->format('Y-m-d') ?? $activo->fecha_adquisicion,
            'fecha_vencimiento' => $activo->fecha_vencimiento?->format('Y-m-d') ?? $activo->fecha_vencimiento,
            'tipo' => $activo->tipo ? [
                'id' => $activo->tipo->id,
                'nombre' => $activo->tipo->nombre,
                'categoria' => $activo->tipo->categoria,
            ] : null,
            'categoria_activo' => $activo->categoria ? [
                'id' => $activo->categoria->id,
                'nombre' => $activo->categoria->nombre,
            ] : null,
            'activo_padre_id' => $activo->activo_padre_id,
            'departamento' => $activo->departamento ? [
                'id' => $activo->departamento->id,
                'nombre' => $activo->departamento->nombre,
                'codigo' => $activo->departamento->codigo,
            ] : null,
            'capturado_en' => now()->toIso8601String(),
        ];
    }
}
