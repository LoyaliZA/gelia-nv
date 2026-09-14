<?php

namespace App\Services\Tiendanube\Precios;

use App\Exceptions\Tiendanube\TiendanubePrecioFuenteException;
use App\Models\Tiendanube\TiendanubePrecioLista;
use Illuminate\Support\Collection;

class TiendanubePrecioListaService
{
    /**
     * @return Collection<int, TiendanubePrecioLista>
     */
    public function listar(int $storeId, bool $incluirArchivadas = true): Collection
    {
        return TiendanubePrecioLista::query()
            ->where('store_id', $storeId)
            ->when(! $incluirArchivadas, fn ($q) => $q->activas())
            ->orderBy('nombre')
            ->get();
    }

    public function crear(int $storeId, string $nombre): TiendanubePrecioLista
    {
        $nombre = $this->validarNombre($nombre);
        $this->assertNombreLibre($storeId, $nombre);

        return TiendanubePrecioLista::create([
            'store_id' => $storeId,
            'nombre' => $nombre,
        ]);
    }

    public function renombrar(TiendanubePrecioLista $lista, string $nombre): TiendanubePrecioLista
    {
        $nombre = $this->validarNombre($nombre);
        $this->assertNombreLibre((int) $lista->store_id, $nombre, (int) $lista->id);
        $lista->nombre = $nombre;
        $lista->save();

        return $lista;
    }

    public function archivar(TiendanubePrecioLista $lista): TiendanubePrecioLista
    {
        if ($lista->archived_at === null) {
            $lista->archived_at = now();
            $lista->save();
        }

        return $lista;
    }

    private function validarNombre(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new TiendanubePrecioFuenteException('El nombre de la lista es obligatorio.', 'nombre_invalido');
        }
        if (mb_strlen($nombre) > 120) {
            throw new TiendanubePrecioFuenteException('El nombre de la lista supera 120 caracteres.', 'nombre_invalido');
        }

        return $nombre;
    }

    private function assertNombreLibre(int $storeId, string $nombre, ?int $exceptoId = null): void
    {
        $existe = TiendanubePrecioLista::query()
            ->where('store_id', $storeId)
            ->activas()
            ->where('nombre', $nombre)
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->exists();

        if ($existe) {
            throw new TiendanubePrecioFuenteException('Ya existe una lista activa con ese nombre.', 'nombre_duplicado');
        }
    }
}
