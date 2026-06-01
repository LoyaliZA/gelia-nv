<?php

namespace App\Services\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\RhColaborador;
use App\Models\User;
use Illuminate\Support\Collection;

class FiltrarReglasIncidenciaService
{
    public function ejecutar(User $usuario, ?RhColaborador $colaborador = null, ?int $reglaIdActual = null): Collection
    {
        $query = CatalogoReglaIncidencia::query()
            ->where('activo', true)
            ->with(['departamentosAplicables', 'areasAplicables', 'departamentosVisibilidad', 'areasVisibilidad', 'bono'])
            ->orderBy('categoria')
            ->orderBy('nombre');

        $reglas = $query->get();

        if (!$colaborador) {
            return $reglas->filter(fn (CatalogoReglaIncidencia $r) => $r->visibleParaUsuario($usuario))->values();
        }

        $filtradas = $reglas->filter(
            fn (CatalogoReglaIncidencia $r) => $r->disponiblePara($colaborador, $usuario),
        );

        if ($reglaIdActual) {
            $actual = $reglas->firstWhere('id', $reglaIdActual);
            if ($actual && !$filtradas->contains('id', $reglaIdActual)) {
                $filtradas = $filtradas->push($actual);
            }
        }

        return $filtradas->values();
    }
}
