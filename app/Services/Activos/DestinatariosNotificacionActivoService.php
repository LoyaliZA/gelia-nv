<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\Area;
use App\Models\User;
use Illuminate\Support\Collection;

class DestinatariosNotificacionActivoService
{
    public function ejecutar(Activo $activo, ?User $usuarioDestino = null, ?User $responsableAnterior = null): Collection
    {
        $ids = collect();

        $ids = $ids->merge($this->usuariosDepartamentoTi());
        $ids = $ids->merge($this->usuariosRecursosHumanos());

        if ($activo->responsable_user_id) {
            $ids->push($activo->responsable_user_id);
        }

        if ($responsableAnterior?->id) {
            $ids->push($responsableAnterior->id);
        }

        if ($usuarioDestino?->id) {
            $ids->push($usuarioDestino->id);
        }

        return User::whereIn('id', $ids->unique()->filter()->values()->all())->get();
    }

    private function usuariosDepartamentoTi(): Collection
    {
        $nombre = config('activos.departamento_ti', 'TI');

        return User::whereHas('departamentos', fn ($q) => $q->where('nombre', $nombre))
            ->pluck('id');
    }

    private function usuariosRecursosHumanos(): Collection
    {
        $nombreArea = config('activos.area_rh', 'Recursos Humanos');
        $areaIds = Area::where('nombre', $nombreArea)->pluck('id');

        if ($areaIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where(function ($q) use ($areaIds) {
                $q->whereIn('area_id', $areaIds)
                    ->orWhereHas('areas', fn ($sub) => $sub->whereIn('areas.id', $areaIds));
            })
            ->pluck('id');
    }
}
