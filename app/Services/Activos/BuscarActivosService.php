<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\CatalogoTipoActivo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BuscarActivosService
{
    public function ejecutar(?User $usuario, string $q = '', ?int $excluirId = null, int $limite = 15): array
    {
        $query = Activo::query()
            ->with(['tipo:id,nombre,slug'])
            ->where('estado', '!=', 'baja')
            ->whereHas('tipo', fn (Builder $tipo) => $tipo->where('slug', '!=', 'accesorio'))
            ->orderByDesc('created_at');

        if ($excluirId) {
            $query->where('id', '!=', $excluirId);
        }

        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('folio', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            });
        }

        if ($usuario && !$usuario->hasRole(['Super Admin', 'Administrador']) && !$usuario->can('activos.ver_todos')) {
            $departamentosUsuario = $usuario->departamentos->pluck('id')->toArray();
            if (!empty($departamentosUsuario)) {
                $query->whereIn('departamento_id', $departamentosUsuario);
            } else {
                return [];
            }
        }

        return $query->limit($limite)->get(['id', 'folio', 'nombre', 'catalogo_tipo_activo_id'])
            ->map(fn (Activo $activo) => [
                'id' => $activo->id,
                'folio' => $activo->folio,
                'nombre' => $activo->nombre,
                'tipo' => $activo->tipo?->nombre,
            ])
            ->all();
    }

    public function validarActivoPadre(
        ?int $activoPadreId,
        int $tipoActivoId,
        ?int $activoId = null,
    ): void {
        $tipo = CatalogoTipoActivo::findOrFail($tipoActivoId);
        $esAccesorio = $tipo->slug === 'accesorio';

        if ($esAccesorio && !$activoPadreId) {
            throw ValidationException::withMessages([
                'activo_padre_id' => 'Debes vincular el accesorio a un activo principal.',
            ]);
        }

        if (!$esAccesorio && $activoPadreId) {
            throw ValidationException::withMessages([
                'activo_padre_id' => 'Solo los activos de tipo Accesorio pueden vincularse a otro activo.',
            ]);
        }

        if (!$activoPadreId) {
            return;
        }

        if ($activoId && $activoPadreId === $activoId) {
            throw ValidationException::withMessages([
                'activo_padre_id' => 'Un activo no puede vincularse a sí mismo.',
            ]);
        }

        $padre = Activo::with('tipo')->find($activoPadreId);
        if (!$padre) {
            throw ValidationException::withMessages([
                'activo_padre_id' => 'El activo principal seleccionado no existe.',
            ]);
        }

        if ($padre->tipo?->slug === 'accesorio') {
            throw ValidationException::withMessages([
                'activo_padre_id' => 'El activo principal no puede ser otro accesorio.',
            ]);
        }

        if ($padre->estado === 'baja') {
            throw ValidationException::withMessages([
                'activo_padre_id' => 'No se puede vincular a un activo dado de baja.',
            ]);
        }
    }
}
