<?php

namespace App\Services\Manuales;

use App\Support\Manuales\ManualesCatalog;
use Illuminate\Contracts\Auth\Access\Authorizable;

class ResolverManualesVisiblesService
{
    public function veTodo(Authorizable $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return true;
        }

        return $user->can('soporte.gestionar') || $user->can('soporte.administrar');
    }

    public function hubVisible(Authorizable $user): bool
    {
        if ($this->veTodo($user)) {
            return true;
        }

        foreach (ManualesCatalog::todosLosPermisosHub() as $permiso) {
            if ($user->can($permiso)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public function listarPara(Authorizable $user): array
    {
        $veTodo = $this->veTodo($user);
        $out = [];

        foreach (ManualesCatalog::todos() as $manual) {
            if (! $veTodo && ! $this->pasaAny($user, $manual['permisosAny'])) {
                continue;
            }

            $secciones = $this->filtrarSecciones($manual['secciones'], $user, $veTodo);
            $out[] = [
                'slug' => $manual['slug'],
                'modulo' => $manual['modulo'],
                'titulo' => $manual['titulo'],
                'descripcion' => $manual['descripcion'],
                'secciones' => $secciones,
                'pdf_url' => route('soporte.manuales.pdf', $manual['slug']),
                'show_url' => route('soporte.manuales.show', $manual['slug']),
            ];
        }

        return $out;
    }

    /**
     * @return array{manual: array<string, mixed>, secciones: list<array<string, mixed>>, ve_todo: bool}|null
     */
    public function resolverShow(string $slug, Authorizable $user): ?array
    {
        $manual = ManualesCatalog::porSlug($slug);
        if (! $manual) {
            return null;
        }

        $veTodo = $this->veTodo($user);
        if (! $veTodo && ! $this->pasaAny($user, $manual['permisosAny'])) {
            return null;
        }

        $secciones = $this->filtrarSecciones($manual['secciones'], $user, $veTodo);

        return [
            'manual' => [
                'slug' => $manual['slug'],
                'modulo' => $manual['modulo'],
                'titulo' => $manual['titulo'],
                'descripcion' => $manual['descripcion'],
            ],
            'secciones' => $secciones,
            've_todo' => $veTodo,
            'pdf_url' => route('soporte.manuales.pdf', $manual['slug']),
        ];
    }

    /**
     * @param  list<array{id: string, cargo: string, titulo: string, permisosAny: list<string>}>  $secciones
     * @return list<array{id: string, cargo: string, titulo: string}>
     */
    public function filtrarSecciones(array $secciones, Authorizable $user, ?bool $veTodo = null): array
    {
        $veTodo ??= $this->veTodo($user);
        $out = [];

        foreach ($secciones as $sec) {
            if ($veTodo || $this->pasaAny($user, $sec['permisosAny'])) {
                $out[] = [
                    'id' => $sec['id'],
                    'cargo' => $sec['cargo'],
                    'titulo' => $sec['titulo'],
                ];
            }
        }

        return $out;
    }

    /** @return list<string> ids de sección visibles */
    public function idsSeccionesVisibles(string $slug, Authorizable $user): array
    {
        $resolved = $this->resolverShow($slug, $user);
        if (! $resolved) {
            return [];
        }

        return array_column($resolved['secciones'], 'id');
    }

    /** @param  list<string>  $permisos */
    private function pasaAny(Authorizable $user, array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if ($user->can($permiso)) {
                return true;
            }
        }

        return false;
    }
}
