<?php

namespace App\Services\Tiendanube\Precios;

use App\Exceptions\Tiendanube\TiendanubePrecioSeleccionException;
use App\Models\Tiendanube\TiendanubePrecioSeleccion;
use App\Models\Tiendanube\TiendanubePrecioSeleccionMiembro;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TiendanubePrecioSeleccionService
{
    public function __construct(
        private readonly TiendanubePrecioCatalogoQueryService $catalogo,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function crear(int $storeId, int $userId, array $datos, bool $puedeVerCosto): array
    {
        $filtros = TiendanubePrecioCatalogoFiltros::fromArray($datos['filtros'] ?? $datos);
        $modo = (string) ($datos['modo'] ?? TiendanubePrecioSeleccion::MODO_PAGINA);
        if (! in_array($modo, [TiendanubePrecioSeleccion::MODO_PAGINA, TiendanubePrecioSeleccion::MODO_TODOS_RESULTADOS], true)) {
            throw new TiendanubePrecioSeleccionException('El modo de selección no es válido.', 'modo_invalido');
        }

        $ids = $modo === TiendanubePrecioSeleccion::MODO_TODOS_RESULTADOS
            ? $this->catalogo->idsUniverso($filtros, $puedeVerCosto)
            : $this->normalizarIds($datos['variante_ids'] ?? []);

        if ($modo === TiendanubePrecioSeleccion::MODO_PAGINA) {
            $ids = $this->soloExistentes($ids);
        }

        $seleccion = TiendanubePrecioSeleccion::query()->create([
            'id' => (string) Str::uuid(),
            'store_id' => $storeId,
            'user_id' => $userId,
            'version' => 1,
            'generacion' => 1,
            'modo' => $modo,
            'filtros' => $filtros->universo(),
            'expires_at' => now()->addDay(),
        ]);

        $this->reemplazarMiembros($seleccion, $ids);
        $this->recontar($seleccion);

        $idsPagina = $this->normalizarIds($datos['page_variante_ids'] ?? []);
        if ($idsPagina === [] && $modo === TiendanubePrecioSeleccion::MODO_PAGINA) {
            $idsPagina = $ids;
        }

        return $this->payload($seleccion->fresh(), $idsPagina);
    }

    /**
     * @param  list<int>  $varianteIdsPagina
     * @return array<string, mixed>
     */
    public function consultar(
        string $id,
        int $storeId,
        int $userId,
        array $varianteIdsPagina = [],
        ?int $version = null,
    ): array {
        $seleccion = $this->obtenerAutorizada($id, $storeId, $userId);
        $this->asegurarVigente($seleccion);

        if ($version !== null && $version !== (int) $seleccion->version) {
            throw new TiendanubePrecioSeleccionException(
                'La versión de la selección no coincide. Recargue e intente de nuevo.',
                'conflicto',
                409
            );
        }

        return $this->payload($seleccion, $varianteIdsPagina);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function actualizar(string $id, int $storeId, int $userId, array $datos, bool $puedeVerCosto): array
    {
        return DB::transaction(function () use ($id, $storeId, $userId, $datos, $puedeVerCosto) {
            $seleccion = $this->obtenerAutorizada($id, $storeId, $userId, true);
            $this->asegurarVigente($seleccion);
            $this->assertVersion($seleccion, $datos['version'] ?? null);

            $accion = (string) ($datos['accion'] ?? '');
            match ($accion) {
                'agregar' => $this->agregar($seleccion, $this->normalizarIds($datos['variante_ids'] ?? [])),
                'quitar' => $this->quitar($seleccion, $this->normalizarIds($datos['variante_ids'] ?? [])),
                'seleccionar_todos' => $this->seleccionarTodos($seleccion, $datos, $puedeVerCosto),
                'reemplazar' => $this->reemplazar($seleccion, $datos, $puedeVerCosto),
                'vaciar' => $this->vaciar($seleccion),
                default => throw new TiendanubePrecioSeleccionException('La acción de selección no es válida.', 'accion_invalida'),
            };

            $seleccion->version = (int) $seleccion->version + 1;
            $seleccion->save();
            $this->recontar($seleccion);

            return $this->payload($seleccion->fresh(), $this->normalizarIds($datos['page_variante_ids'] ?? []));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function resolver(string $id, int $storeId, int $userId, int $page = 1, int $perPage = 100): array
    {
        $seleccion = $this->obtenerAutorizada($id, $storeId, $userId);
        $this->asegurarVigente($seleccion);

        $perPage = max(1, min(500, $perPage));
        $page = max(1, $page);

        $query = $seleccion->miembros()->orderBy('variante_id');
        $total = (clone $query)->count();
        $ids = $query->forPage($page, $perPage)->pluck('variante_id')->map(fn ($v) => (int) $v)->all();
        $existentes = $this->existentesIndex($ids);
        $validos = array_values(array_filter($ids, fn (int $vid) => isset($existentes[$vid])));
        $invalidosPagina = array_values(array_filter($ids, fn (int $vid) => ! isset($existentes[$vid])));

        return $this->payload($seleccion, []) + [
            'variante_ids' => $validos,
            'variante_ids_invalidos' => $invalidosPagina,
            'resolver_meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @param  list<int>  $varianteIdsPagina
     * @return array<string, mixed>
     */
    public function adjuntarAListado(string $id, int $storeId, int $userId, TiendanubePrecioCatalogoFiltros $filtrosActuales, array $varianteIdsPagina, bool $puedeVerCosto): array
    {
        $seleccion = $this->obtenerAutorizada($id, $storeId, $userId);
        $this->asegurarVigente($seleccion);
        $payload = $this->payload($seleccion, $varianteIdsPagina);
        $universoSel = TiendanubePrecioCatalogoFiltros::fromArray($seleccion->filtros ?? []);
        $alineada = $universoSel->equivaleUniverso($filtrosActuales->universo());
        $payload['filtros_alineados'] = $alineada;
        if (! $alineada) {
            $coinciden = $this->catalogo->contarCoincidentes(
                $filtrosActuales,
                $seleccion->miembros()->pluck('variante_id')->map(fn ($v) => (int) $v)->all(),
                $puedeVerCosto
            );
            $payload['filas_ocultas'] = max(0, (int) $seleccion->total_variantes - $coinciden);
        } else {
            $payload['filas_ocultas'] = 0;
        }

        return $payload;
    }

    private function obtenerAutorizada(string $id, int $storeId, int $userId, bool $bloquear = false): TiendanubePrecioSeleccion
    {
        $query = TiendanubePrecioSeleccion::query()->where('id', $id);
        if ($bloquear) {
            $query->lockForUpdate();
        }
        $seleccion = $query->first();
        if (! $seleccion) {
            throw new TiendanubePrecioSeleccionException('La selección no existe.', 'no_encontrada', 404);
        }
        if ((int) $seleccion->user_id !== $userId || (int) $seleccion->store_id !== $storeId) {
            throw new TiendanubePrecioSeleccionException('No se puede usar esta selección.', 'no_autorizado', 403);
        }

        return $seleccion;
    }

    private function asegurarVigente(TiendanubePrecioSeleccion $seleccion): void
    {
        if ($seleccion->invalidated_at !== null) {
            throw new TiendanubePrecioSeleccionException('La selección ya no está vigente.', 'invalidada', 410);
        }
        if ($seleccion->expirada()) {
            throw new TiendanubePrecioSeleccionException('La selección de borrador venció.', 'expirada', 410);
        }
    }

    private function assertVersion(TiendanubePrecioSeleccion $seleccion, mixed $version): void
    {
        if ($version === null || (int) $version !== (int) $seleccion->version) {
            throw new TiendanubePrecioSeleccionException(
                'Otro cambio se guardó primero. Conserve el formulario y recargue la selección.',
                'conflicto',
                409
            );
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function agregar(TiendanubePrecioSeleccion $seleccion, array $ids): void
    {
        $ids = $this->soloExistentes($ids);
        $this->insertarMiembros($seleccion, $ids);
    }

    /**
     * @param  list<int>  $ids
     */
    private function quitar(TiendanubePrecioSeleccion $seleccion, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $seleccion->miembros()->whereIn('variante_id', $ids)->delete();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function seleccionarTodos(TiendanubePrecioSeleccion $seleccion, array $datos, bool $puedeVerCosto): void
    {
        $filtros = TiendanubePrecioCatalogoFiltros::fromArray($datos['filtros'] ?? $seleccion->filtros ?? []);
        $ids = $this->catalogo->idsUniverso($filtros, $puedeVerCosto);
        $seleccion->modo = TiendanubePrecioSeleccion::MODO_TODOS_RESULTADOS;
        $seleccion->filtros = $filtros->universo();
        $seleccion->generacion = (int) $seleccion->generacion + 1;
        $this->reemplazarMiembros($seleccion, $ids);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function reemplazar(TiendanubePrecioSeleccion $seleccion, array $datos, bool $puedeVerCosto): void
    {
        $filtros = TiendanubePrecioCatalogoFiltros::fromArray($datos['filtros'] ?? []);
        $modo = (string) ($datos['modo'] ?? TiendanubePrecioSeleccion::MODO_PAGINA);
        $ids = $modo === TiendanubePrecioSeleccion::MODO_TODOS_RESULTADOS
            ? $this->catalogo->idsUniverso($filtros, $puedeVerCosto)
            : $this->soloExistentes($this->normalizarIds($datos['variante_ids'] ?? []));

        $seleccion->modo = $modo;
        $seleccion->filtros = $filtros->universo();
        $seleccion->generacion = (int) $seleccion->generacion + 1;
        $this->reemplazarMiembros($seleccion, $ids);
    }

    private function vaciar(TiendanubePrecioSeleccion $seleccion): void
    {
        $seleccion->miembros()->delete();
        $seleccion->modo = TiendanubePrecioSeleccion::MODO_PAGINA;
        $seleccion->generacion = (int) $seleccion->generacion + 1;
    }

    /**
     * @param  list<int>  $ids
     */
    private function reemplazarMiembros(TiendanubePrecioSeleccion $seleccion, array $ids): void
    {
        $seleccion->miembros()->delete();
        $this->insertarMiembros($seleccion, $ids);
    }

    /**
     * @param  list<int>  $ids
     */
    private function insertarMiembros(TiendanubePrecioSeleccion $seleccion, array $ids): void
    {
        $ids = array_values(array_unique($ids));
        $ahora = now();
        foreach (array_chunk($ids, 500) as $chunk) {
            $filas = [];
            foreach ($chunk as $vid) {
                $filas[] = [
                    'seleccion_id' => $seleccion->id,
                    'variante_id' => $vid,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
            TiendanubePrecioSeleccionMiembro::query()->insert($filas);
        }
    }

    private function recontar(TiendanubePrecioSeleccion $seleccion): void
    {
        $ids = $seleccion->miembros()->pluck('variante_id')->map(fn ($v) => (int) $v)->all();
        $existentes = $this->existentesIndex($ids);
        $validos = array_values(array_filter($ids, fn (int $id) => isset($existentes[$id])));
        $productos = $validos === []
            ? 0
            : TiendanubeProductoVariante::query()->whereIn('id', $validos)->distinct()->count('producto_id');

        $seleccion->total_variantes = count($ids);
        $seleccion->total_productos = $productos;
        $seleccion->save();
    }

    /**
     * @param  list<int>  $varianteIdsPagina
     * @return array<string, mixed>
     */
    private function payload(TiendanubePrecioSeleccion $seleccion, array $varianteIdsPagina): array
    {
        $ids = $seleccion->miembros()->pluck('variante_id')->map(fn ($v) => (int) $v)->all();
        $existentes = $this->existentesIndex($ids);
        $invalidos = count($ids) - count(array_filter($ids, fn (int $id) => isset($existentes[$id])));

        $seleccionadosPagina = [];
        if ($varianteIdsPagina !== []) {
            $set = array_fill_keys($ids, true);
            foreach ($varianteIdsPagina as $vid) {
                if (isset($set[$vid])) {
                    $seleccionadosPagina[] = $vid;
                }
            }
        }

        return [
            'selection_id' => $seleccion->id,
            'version' => (int) $seleccion->version,
            'store_id' => (int) $seleccion->store_id,
            'modo' => $seleccion->modo,
            'generacion' => (int) $seleccion->generacion,
            'total_variantes' => (int) $seleccion->total_variantes,
            'total_productos' => (int) $seleccion->total_productos,
            'filtros' => $seleccion->filtros,
            'miembros_invalidos' => $invalidos,
            'expires_at' => $seleccion->expires_at?->toIso8601String(),
            'seleccionados_pagina' => $seleccionadosPagina,
        ];
    }

    /**
     * @return list<int>
     */
    private function normalizarIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $raw)));
        if (count($ids) > 5000) {
            throw new TiendanubePrecioSeleccionException('Demasiados identificadores en una sola petición.', 'limite');
        }

        return array_values(array_filter($ids, fn (int $id) => $id > 0));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function soloExistentes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return TiendanubeProductoVariante::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function existentesIndex(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $index = [];
        foreach (TiendanubeProductoVariante::query()->whereIn('id', $ids)->pluck('id') as $id) {
            $index[(int) $id] = true;
        }

        return $index;
    }
}
