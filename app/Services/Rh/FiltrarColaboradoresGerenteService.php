<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FiltrarColaboradoresGerenteService
{
    public function ejecutar(User $gerente): Collection
    {
        $asignados = $gerente->colaboradoresRhAsignados()
            ->where('activo', true)
            ->with(['departamento', 'area'])
            ->orderBy('nombre')
            ->get();

        if ($asignados->isNotEmpty()) {
            return $asignados;
        }

        $deptoIds = $gerente->departamentos()->pluck('departamentos.id');
        $areaIds = $gerente->areas()->pluck('areas.id');

        if ($gerente->area_id) {
            $areaIds = $areaIds->push($gerente->area_id)->unique();
        }

        if ($deptoIds->isEmpty() && $areaIds->isEmpty()) {
            return collect();
        }

        return RhColaborador::query()
            ->where('activo', true)
            ->where(function ($q) use ($deptoIds, $areaIds) {
                if ($deptoIds->isNotEmpty()) {
                    $q->orWhereIn('departamento_id', $deptoIds);
                }
                if ($areaIds->isNotEmpty()) {
                    $q->orWhereIn('area_id', $areaIds);
                }
            })
            ->with(['departamento', 'area'])
            ->orderBy('nombre')
            ->get();
    }

    public function puedeAcceder(User $usuario, RhColaborador $colaborador): bool
    {
        if ($usuario->can('rh.deducciones.ver') || $usuario->can('rh.incidencias.ver')) {
            return true;
        }

        if (!$usuario->can('rh.incidencias.gerente.ver') && !$usuario->can('rh.incidencias.gerente.crear')) {
            return false;
        }

        return $this->ejecutar($usuario)->contains('id', $colaborador->id);
    }

    public function validarAcceso(User $usuario, RhColaborador $colaborador): void
    {
        if (!$this->puedeAcceder($usuario, $colaborador)) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'No tiene autorización para este colaborador.',
            ]);
        }
    }
}
