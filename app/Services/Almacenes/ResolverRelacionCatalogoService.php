<?php

namespace App\Services\Almacenes;

use App\Models\CatalogoCategoriaProducto;
use App\Models\CatalogoMarcaProducto;
use RuntimeException;

class ResolverRelacionCatalogoService
{
    public function __construct(
        private readonly NormalizarTextoImportacionService $normalizador,
    ) {}

    public function marcaId(?string $nombre): ?int
    {
        $normalizado = $this->normalizador->texto($nombre);
        if ($normalizado === null) {
            return null;
        }

        $marca = CatalogoMarcaProducto::whereRaw('UPPER(nombre) = ?', [$normalizado])->first();
        if (! $marca) {
            throw new RuntimeException("Marca «{$normalizado}» no existe en catálogo.");
        }

        return $marca->id;
    }

    public function categoriaId(?string $nombre): ?int
    {
        $normalizado = $this->normalizador->texto($nombre);
        if ($normalizado === null) {
            return null;
        }

        $categoria = CatalogoCategoriaProducto::whereRaw('UPPER(nombre) = ?', [$normalizado])->first();
        if (! $categoria) {
            throw new RuntimeException("Categoría «{$normalizado}» no existe en catálogo.");
        }

        return $categoria->id;
    }
}
