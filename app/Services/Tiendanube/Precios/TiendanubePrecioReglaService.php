<?php

namespace App\Services\Tiendanube\Precios;

use App\Exceptions\Tiendanube\TiendanubePrecioReglaException;
use App\Models\Tiendanube\TiendanubePrecioRegla;
use Illuminate\Support\Facades\DB;

class TiendanubePrecioReglaService
{
    public function __construct(
        private readonly TiendanubePrecioReglaVersionService $versiones = new TiendanubePrecioReglaVersionService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(int $storeId, bool $incluirArchivadas = false): array
    {
        $query = TiendanubePrecioRegla::query()
            ->with('versionActual')
            ->where('store_id', $storeId)
            ->orderBy('nombre');

        if (! $incluirArchivadas) {
            $query->activas();
        }

        return $query->get()->map(fn (TiendanubePrecioRegla $r) => $this->serializarResumen($r))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function obtener(int $id, int $storeId): array
    {
        $regla = $this->buscar($id, $storeId);

        return $this->serializarDetalle($regla);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function crear(int $storeId, int $userId, array $datos): array
    {
        return DB::transaction(function () use ($storeId, $userId, $datos) {
            $regla = TiendanubePrecioRegla::query()->create([
                'store_id' => $storeId,
                'nombre' => (string) $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'habilitada' => (bool) ($datos['habilitada'] ?? true),
                'version' => 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $definicion = $this->normalizarDefinicion($datos['definicion'] ?? [], $regla->id);
            $version = $this->versiones->crear($regla, $definicion, $userId, $storeId);

            $regla->version_actual_id = $version->id;
            $regla->save();

            return $this->serializarDetalle($regla->fresh(['versionActual']));
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function actualizar(int $id, int $storeId, int $userId, array $datos): array
    {
        return DB::transaction(function () use ($id, $storeId, $userId, $datos) {
            $regla = $this->buscar($id, $storeId, true);
            $this->assertVersion($regla, $datos['version'] ?? null);

            if (array_key_exists('nombre', $datos)) {
                $regla->nombre = (string) $datos['nombre'];
            }
            if (array_key_exists('descripcion', $datos)) {
                $regla->descripcion = $datos['descripcion'];
            }
            if (array_key_exists('habilitada', $datos)) {
                $regla->habilitada = (bool) $datos['habilitada'];
            }

            if (isset($datos['definicion'])) {
                $definicion = $this->normalizarDefinicion($datos['definicion'], $regla->id);
                $version = $this->versiones->crear($regla, $definicion, $userId, $storeId);
                $regla->version_actual_id = $version->id;
            }

            $regla->version = (int) $regla->version + 1;
            $regla->updated_by = $userId;
            $regla->save();

            return $this->serializarDetalle($regla->fresh(['versionActual']));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function duplicar(int $id, int $storeId, int $userId): array
    {
        $origen = $this->buscar($id, $storeId);
        $origen->loadMissing('versionActual');
        $definicion = $origen->versionActual?->definicion;
        if (! is_array($definicion)) {
            throw new TiendanubePrecioReglaException('La regla no tiene versión para duplicar.', 'no_encontrada', 404);
        }

        return $this->crear($storeId, $userId, [
            'nombre' => $origen->nombre.' (copia)',
            'descripcion' => $origen->descripcion,
            'habilitada' => true,
            'definicion' => $definicion,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function archivar(int $id, int $storeId, int $userId): array
    {
        $regla = $this->buscar($id, $storeId);
        $regla->archived_at = now();
        $regla->habilitada = false;
        $regla->updated_by = $userId;
        $regla->save();

        return $this->serializarDetalle($regla->fresh(['versionActual']));
    }

    public function buscar(int $id, int $storeId, bool $lock = false): TiendanubePrecioRegla
    {
        $query = TiendanubePrecioRegla::query()->where('id', $id)->where('store_id', $storeId);
        if ($lock) {
            $query->lockForUpdate();
        }

        $regla = $query->first();
        if (! $regla) {
            throw new TiendanubePrecioReglaException('Regla no encontrada.', 'no_encontrada', 404);
        }

        return $regla;
    }

    /**
     * @param  array<string, mixed>  $definicion
     * @return array<string, mixed>
     */
    public function normalizarDefinicion(array $definicion, int $reglaId): array
    {
        $definicion['id'] = 'regla-'.$reglaId;

        return $definicion;
    }

    private function assertVersion(TiendanubePrecioRegla $regla, mixed $version): void
    {
        if ($version === null) {
            throw new TiendanubePrecioReglaException('Falta la versión de concurrencia.', 'validacion', 422);
        }

        if ((int) $version !== (int) $regla->version) {
            throw new TiendanubePrecioReglaException(
                'La regla fue modificada por otra persona. Recargue e intente de nuevo.',
                'conflicto',
                409
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResumen(TiendanubePrecioRegla $regla): array
    {
        $version = $regla->versionActual;

        return [
            'id' => $regla->id,
            'nombre' => $regla->nombre,
            'descripcion' => $regla->descripcion,
            'habilitada' => (bool) $regla->habilitada,
            'archivada' => $regla->archivada(),
            'version' => (int) $regla->version,
            'version_actual' => $version ? $this->versiones->serializar($version) : null,
            'updated_at' => $regla->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarDetalle(TiendanubePrecioRegla $regla): array
    {
        $regla->loadMissing('versionActual');

        return $this->serializarResumen($regla) + [
            'store_id' => (int) $regla->store_id,
            'versiones' => $regla->versiones()
                ->orderByDesc('numero')
                ->limit(20)
                ->get()
                ->map(fn ($v) => $this->versiones->serializar($v))
                ->values()
                ->all(),
        ];
    }
}
