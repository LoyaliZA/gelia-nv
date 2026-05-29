<?php

namespace App\Services\Activos;

use App\Models\CatalogoMarcaActivo;
use App\Models\CatalogoModeloActivo;
use Illuminate\Support\Str;

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

    public function modelos(?int $marcaId = null, ?string $q = null, ?int $tipoId = null, ?string $marcaNombre = null, int $limite = 30): array
    {
        if (!$marcaId && $marcaNombre && $tipoId) {
            $slug = Str::slug(trim($marcaNombre));
            $marca = CatalogoMarcaActivo::query()
                ->where('catalogo_tipo_activo_id', $tipoId)
                ->where(function ($query) use ($marcaNombre, $slug) {
                    $query->where('nombre', trim($marcaNombre))
                        ->orWhere('slug', $slug);
                })
                ->first();
            $marcaId = $marca?->id;
        }

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
