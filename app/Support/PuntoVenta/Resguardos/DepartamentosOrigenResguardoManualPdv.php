<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class DepartamentosOrigenResguardoManualPdv
{
    /** @var list<string> */
    public const NOMBRES = ['Aromas', 'Bellaroma'];

    public static function query(): Builder
    {
        return Departamento::query()
            ->where('activo', true)
            ->where(function (Builder $query): void {
                foreach (self::NOMBRES as $nombre) {
                    $query->orWhere('nombre', $nombre)
                        ->orWhere('nombre', 'like', $nombre.' %');
                }
            })
            ->orderBy('nombre');
    }

    /**
     * @return Collection<int, Departamento>
     */
    public static function listar(): Collection
    {
        return self::query()->get(['id', 'nombre']);
    }

    public static function encontrarActivo(int $id): ?Departamento
    {
        $departamento = self::query()->find($id);

        return $departamento instanceof Departamento ? $departamento : null;
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public static function serializar(): array
    {
        return self::listar()
            ->map(static fn (Departamento $departamento): array => [
                'id' => $departamento->id,
                'nombre' => $departamento->nombre,
            ])
            ->values()
            ->all();
    }
}
