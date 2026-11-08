<?php

namespace App\Services\ApiExterna;

use App\Models\ApiAplicacion;
use App\Models\ApiAplicacionCampo;
use App\Models\ApiAplicacionPermiso;
use App\Models\ApiCampoRecurso;
use App\Models\ApiRecurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

            // Sensibles: solo con opt-in explícito por aplicación.
            if ($campo->es_sensible) {
                return $override !== null && $override->habilitado;
            }

            if ($override === null) {
                return true;
            }

            return $override->habilitado;
        })->values();
    }

    public function sincronizarPermisosAplicacion(ApiAplicacion $aplicacion): void
    {
        $recursos = ApiRecurso::where('activo', true)->with('campos')->get();

        foreach ($recursos as $recurso) {
            ApiAplicacionPermiso::firstOrCreate(
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
                    // Apps nuevas: sensibles apagados hasta opt-in en admin.
                    ['habilitado' => $campo->es_sensible ? false : $campo->habilitado_global]
                );
            }
        }
    }

    public function sincronizarCatalogo(ApiAplicacion $aplicacion): void
    {
        $firma = $this->firmaCatalogo();
        $clave = $this->claveCacheCatalogo($aplicacion);

        if (Cache::get($clave) === $firma) {
            return;
        }

        $this->sincronizarPermisosAplicacion($aplicacion);
        Cache::forever($clave, $firma);
    }

    public function sincronizarCatalogoEnTodas(): void
    {
        ApiAplicacion::query()->orderBy('id')->each(function (ApiAplicacion $aplicacion): void {
            $this->sincronizarCatalogo($aplicacion);
        });
    }

    private function firmaCatalogo(): string
    {
        $recursos = DB::table('api_recursos')
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as actualizado')
            ->first();
        $campos = DB::table('api_campos_recurso')
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as actualizado')
            ->first();

        return implode('|', [
            (int) ($recursos->total ?? 0),
            (string) ($recursos->actualizado ?? ''),
            (int) ($campos->total ?? 0),
            (string) ($campos->actualizado ?? ''),
        ]);
    }

    private function claveCacheCatalogo(ApiAplicacion $aplicacion): string
    {
        return 'api-externa:catalogo:'.$aplicacion->id;
    }
}
