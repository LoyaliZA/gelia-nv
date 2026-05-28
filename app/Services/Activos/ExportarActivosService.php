<?php

namespace App\Services\Activos;

use App\Models\User;
use Illuminate\Support\Collection;

class ExportarActivosService
{
    public function __construct(
        private ListarActivosService $listarActivos,
    ) {}

    public function ejecutar(?User $usuario, array $filtros = []): Collection
    {
        return $this->listarActivos->ejecutar($usuario, $filtros, false);
    }

    public function filas(Collection $activos): array
    {
        return $activos->map(function ($activo) {
            return [
                'Folio' => $activo->folio,
                'Nombre' => $activo->nombre,
                'Marca' => $activo->atributos['marca'] ?? '',
                'Modelo' => $activo->atributos['modelo'] ?? '',
                'Tipo' => $activo->tipo?->nombre,
                'Departamento' => $activo->departamento?->nombre,
                'Área' => $activo->area?->nombre,
                'Estado' => $activo->estado,
                'Pertenece a' => $activo->responsable?->name,
                'Email responsable' => $activo->responsable?->email,
                'Fecha adquisición' => $activo->fecha_adquisicion?->format('Y-m-d'),
                'Fecha vencimiento' => $activo->fecha_vencimiento?->format('Y-m-d'),
                'Valor' => $activo->valor,
                'Descripción' => $activo->descripcion,
                'Atributos' => json_encode($activo->atributos, JSON_UNESCAPED_UNICODE),
            ];
        })->all();
    }
}
