<?php

namespace App\Services\ApiExterna;

use App\Models\ApiAplicacion;
use App\Models\ApiAplicacionCampo;
use App\Models\ApiAplicacionPermiso;
use App\Models\ApiCampoRecurso;
use App\Models\ApiRecurso;
use Illuminate\Support\Collection;

class ApiPermisoService
{
    public function recursoPorSlug(string $slug): ?ApiRecurso
    {
        return ApiRecurso::where('slug', $slug)->first();
    }

    public function puedeLeer(ApiAplicacion $aplicacion, ApiRecurso $recurso): bool
    {
        if (!$recurso->activo || !$recurso->lectura_habilitada) {
            return false;
        }

        $permiso = $this->permisoAplicacion($aplicacion, $recurso);

        return $permiso && $permiso->activo && $permiso->puede_leer;
    }

    public function puedeEscribir(ApiAplicacion $aplicacion, ApiRecurso $recurso): bool
    {
        if (!$recurso->activo || !$recurso->escritura_habilitada) {
            return false;
        }

        $permiso = $this->permisoAplicacion($aplicacion, $recurso);

        return $permiso && $permiso->activo && $permiso->puede_escribir;
    }

    public function permisoAplicacion(ApiAplicacion $aplicacion, ApiRecurso $recurso): ?ApiAplicacionPermiso
    {
        return ApiAplicacionPermiso::where('api_aplicacion_id', $aplicacion->id)
            ->where('api_recurso_id', $recurso->id)
            ->first();
    }

    public function camposHabilitados(ApiAplicacion $aplicacion, ApiRecurso $recurso): Collection
    {
        $camposGlobales = ApiCampoRecurso::where('api_recurso_id', $recurso->id)
            ->where('habilitado_global', true)
            ->orderBy('orden')
            ->get();

        $overrides = ApiAplicacionCampo::where('api_aplicacion_id', $aplicacion->id)
            ->whereIn('api_campo_recurso_id', $camposGlobales->pluck('id'))
            ->get()
            ->keyBy('api_campo_recurso_id');

        return $camposGlobales->filter(function (ApiCampoRecurso $campo) use ($overrides) {
            $override = $overrides->get($campo->id);

            if ($override === null) {
                return true;
            }

            return $override->habilitado;
        })->values();
    }

    public function sincronizarPermisosAplicacion(ApiAplicacion $aplicacion): void
    {
        $recursos = ApiRecurso::where('activo', true)->get();

        foreach ($recursos as $recurso) {
            $permiso = ApiAplicacionPermiso::firstOrCreate(
                [
                    'api_aplicacion_id' => $aplicacion->id,
                    'api_recurso_id' => $recurso->id,
                ],
                [
                    'puede_leer' => true,
                    'puede_escribir' => false,
                    'activo' => true,
                ]
            );

            foreach ($recurso->campos as $campo) {
                ApiAplicacionCampo::firstOrCreate(
                    [
                        'api_aplicacion_id' => $aplicacion->id,
                        'api_campo_recurso_id' => $campo->id,
                    ],
                    ['habilitado' => $campo->habilitado_global]
                );
            }
        }
    }
}
