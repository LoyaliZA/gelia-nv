<?php

namespace App\Services\Tiendanube\Precios;

use App\Exceptions\Tiendanube\TiendanubePrecioReglaException;
use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Models\Tiendanube\TiendanubePrecioRegla;
use App\Models\Tiendanube\TiendanubePrecioReglaVersion;
use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;

final class TiendanubePrecioReglaVersionService
{
    public function __construct(
        private readonly TiendanubePrecioMotorValidador $validador = new TiendanubePrecioMotorValidador,
    ) {}

    /**
     * @param  array<string, mixed>  $definicion
     * @return array{utilizable: bool, motivo: string|null}
     */
    public function evaluarUtilizable(int $storeId, array $definicion, string $contractVersion): array
    {
        if ($contractVersion !== TiendanubePrecioMotorCalculoService::MOTOR_VERSION) {
            return [
                'utilizable' => false,
                'motivo' => 'contract_version_incompatible',
            ];
        }

        $motivos = [];

        if (isset($definicion['base_lista_id'])) {
            $lista = TiendanubePrecioLista::query()
                ->where('id', (int) $definicion['base_lista_id'])
                ->where('store_id', $storeId)
                ->first();

            if (! $lista) {
                $motivos[] = 'lista_base_inexistente';
            } elseif ($lista->archivada()) {
                $motivos[] = 'lista_base_archivada';
            }
        }

        foreach ($definicion['condiciones'] ?? [] as $condicion) {
            if (($condicion['campo'] ?? '') !== TiendanubePrecioCampoCondicion::ListaReferencia->value) {
                continue;
            }
            if (! isset($condicion['lista_id'])) {
                continue;
            }

            $lista = TiendanubePrecioLista::query()
                ->where('id', (int) $condicion['lista_id'])
                ->where('store_id', $storeId)
                ->first();

            if (! $lista) {
                $motivos[] = 'lista_condicion_inexistente';
            } elseif ($lista->archivada()) {
                $motivos[] = 'lista_condicion_archivada';
            }
        }

        return [
            'utilizable' => $motivos === [],
            'motivo' => $motivos === [] ? null : implode(',', array_unique($motivos)),
        ];
    }

    /**
     * @param  array<string, mixed>  $definicion
     */
    public function validarDefinicion(array $definicion): void
    {
        $errores = $this->validador->validarReglaArray($definicion);
        if ($errores !== []) {
            throw new TiendanubePrecioReglaException(
                'La definición de la regla no es válida.',
                'validacion',
                422,
                $errores
            );
        }
    }

    /**
     * @param  array<string, mixed>  $definicion
     */
    public function validarListasTienda(int $storeId, array $definicion): void
    {
        $ids = [];

        if (isset($definicion['base_lista_id'])) {
            $ids[] = (int) $definicion['base_lista_id'];
        }

        foreach ($definicion['condiciones'] ?? [] as $condicion) {
            if (isset($condicion['lista_id'])) {
                $ids[] = (int) $condicion['lista_id'];
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        $enTienda = TiendanubePrecioLista::query()
            ->where('store_id', $storeId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($enTienda) !== count($ids)) {
            throw new TiendanubePrecioReglaException(
                'Una o más listas no pertenecen a la tienda activa.',
                'tienda_invalida',
                422
            );
        }
    }

    public function crear(
        TiendanubePrecioRegla $regla,
        array $definicion,
        int $userId,
        int $storeId
    ): TiendanubePrecioReglaVersion {
        $this->validarDefinicion($definicion);
        $this->validarListasTienda($storeId, $definicion);

        $numero = (int) ($regla->versiones()->max('numero') ?? 0) + 1;
        $contractVersion = TiendanubePrecioMotorCalculoService::MOTOR_VERSION;
        $eval = $this->evaluarUtilizable($storeId, $definicion, $contractVersion);

        return TiendanubePrecioReglaVersion::query()->create([
            'regla_id' => $regla->id,
            'numero' => $numero,
            'contract_version' => $contractVersion,
            'definicion' => $definicion,
            'utilizable' => $eval['utilizable'],
            'motivo_no_utilizable' => $eval['motivo'],
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(TiendanubePrecioReglaVersion $version): array
    {
        return [
            'id' => $version->id,
            'numero' => (int) $version->numero,
            'contract_version' => $version->contract_version,
            'definicion' => $version->definicion,
            'utilizable' => (bool) $version->utilizable,
            'motivo_no_utilizable' => $version->motivo_no_utilizable,
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
