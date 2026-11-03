<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\User;

class ResolverCodigoActivoService
{
    public function resolver(User $usuario, string $codigo): ?Activo
    {
        if (preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $codigo, $coincidencias)) {
            $activo = Activo::query()->where('consulta_token', $coincidencias[0])->first();
            if ($activo && $activo->estado !== 'baja') {
                $this->autorizarAcceso($usuario, $activo);

                return $activo;
            }
        }

        return $this->buscarPorCodigo($usuario, $codigo);
    }

    private function buscarPorCodigo(User $usuario, string $codigo): ?Activo
    {
        $query = Activo::query()->where('estado', '!=', 'baja');

        if (! $usuario->hasRole(['Super Admin', 'Administrador']) && ! $usuario->can('activos.ver_todos')) {
            $departamentos = $usuario->departamentos->pluck('id')->all();
            if ($departamentos === []) {
                return null;
            }
            $query->whereIn('departamento_id', $departamentos);
        }

        $codigoLike = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $codigo).'%';

        return $query->where(function ($q) use ($codigo, $codigoLike) {
            $q->where('folio', $codigo)
                ->orWhere('nombre', 'like', $codigoLike)
                ->orWhere('atributos->serial', $codigo)
                ->orWhere('atributos->mac', $codigo)
                ->orWhere('atributos->mac', 'like', $codigoLike)
                ->orWhere('atributos->ip', $codigo)
                ->orWhere('atributos->numero_serie', $codigo)
                ->orWhere('atributos->no_serie', $codigo)
                ->orWhere('atributos->imei', $codigo);
        })->first();
    }

    private function autorizarAcceso(User $usuario, Activo $activo): void
    {
        if ($usuario->hasRole(['Super Admin', 'Administrador']) || $usuario->can('activos.ver_todos')) {
            return;
        }

        $departamentos = $usuario->departamentos->pluck('id')->all();
        if (! in_array($activo->departamento_id, $departamentos, true)) {
            abort(403, 'No tiene acceso a este activo.');
        }
    }
}
