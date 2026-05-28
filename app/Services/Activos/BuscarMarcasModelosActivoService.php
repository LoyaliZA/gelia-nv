<?php

namespace App\Services\Activos;

use App\Models\CatalogoMarcaActivo;
use App\Models\CatalogoModeloActivo;

class BuscarMarcasModelosActivoService
{
    public function marcas(?int $tipoId = null, ?string $q = null, int $limite = 30): array
    {
        $query = CatalogoMarcaActivo::query()->where('activo', true)->orderBy('nombre');

        if ($tipoId) {
            $query->where('catalogo_tipo_activo_id', $tipoId);
        }

        if ($q) {
            $query->where('nombre', 'like', "%{$q}%");
        }

        return $query->limit($limite)->get(['id', 'nombre', 'catalogo_tipo_activo_id'])->toArray();
    }

    public function modelos(?int $marcaId = null, ?string $q = null, int $limite = 30): array
    {
        if (!$marcaId) {
            return [];
        }

        $query = CatalogoModeloActivo::query()
            ->where('catalogo_marca_activo_id', $marcaId)
            ->where('activo', true)
            ->orderBy('nombre');

        if ($q) {
            $query->where('nombre', 'like', "%{$q}%");
        }

        return $query->limit($limite)->get(['id', 'nombre', 'catalogo_marca_activo_id'])->toArray();
    }
}
