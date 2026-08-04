<?php

namespace App\Services\GeliaAi;

use App\Models\Almacen;

class ResolverAlmacenGeliaAi
{
    public function resolver(?int $almacenId, ?string $codigoONombre): ?Almacen
    {
        if ($almacenId) {
            return Almacen::query()->where('id', $almacenId)->where('activo', true)->first();
        }

        $q = trim((string) $codigoONombre);
        if ($q === '') {
            return null;
        }

        return Almacen::query()
            ->where('activo', true)
            ->where(function ($builder) use ($q) {
                $builder->where('codigo', $q)
                    ->orWhere('codigo', 'like', $q.'%')
                    ->orWhere('nombre', 'like', '%'.$q.'%');
            })
            ->orderByRaw('CASE WHEN codigo = ? THEN 0 ELSE 1 END', [$q])
            ->first();
    }
}
