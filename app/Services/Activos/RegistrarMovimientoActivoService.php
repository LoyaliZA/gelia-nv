<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoMovimiento;
use App\Models\User;

class RegistrarMovimientoActivoService
{
    public function ejecutar(
        Activo $activo,
        User $usuario,
        string $tipo,
        array $opciones = []
    ): ActivoMovimiento {
        return ActivoMovimiento::create([
            'activo_id' => $activo->id,
            'usuario_id' => $usuario->id,
            'tipo' => $tipo,
            'departamento_origen_id' => $opciones['departamento_origen_id'] ?? null,
            'departamento_destino_id' => $opciones['departamento_destino_id'] ?? null,
            'user_destino_id' => $opciones['user_destino_id'] ?? null,
            'estado_anterior' => $opciones['estado_anterior'] ?? null,
            'estado_nuevo' => $opciones['estado_nuevo'] ?? null,
            'motivo' => $opciones['motivo'] ?? null,
            'notas' => $opciones['notas'] ?? null,
            'datos_snapshot' => $opciones['datos_snapshot'] ?? $activo->fresh(['tipo', 'departamento', 'area', 'responsable'])?->toArray(),
        ]);
    }
}
