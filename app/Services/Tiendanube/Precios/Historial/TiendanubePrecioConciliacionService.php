<?php

namespace App\Services\Tiendanube\Precios\Historial;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Models\Tiendanube\TiendanubePrecioConciliacion;
use App\Models\Tiendanube\TiendanubePrecioCsvArtefacto;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubePrecioLoteItem;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionControlRemotoService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TiendanubePrecioConciliacionService
{
    public function __construct(
        private readonly TiendanubePrecioHistorialQueryService $historial,
        private readonly TiendanubePrecioLoteService $lotes,
        private readonly TiendanubeApiClient $api,
        private readonly TiendanubePrecioEjecucionControlRemotoService $control,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function conciliar(string $loteId, int $storeId, int $userId, array $datos, bool $puedeVerCosto): array
    {
        $lote = $this->historial->obtenerDeTienda($loteId, $storeId);
        $revision = $lote->revisionActual();
        if (! $revision) {
            throw new TiendanubePrecioLoteException('El lote no tiene revisión.', 'no_encontrada', 404);
        }

        $tipo = (string) ($datos['evidencia_tipo'] ?? TiendanubePrecioConciliacion::EVIDENCIA_LECTURA_API);
        if (! in_array($tipo, [
            TiendanubePrecioConciliacion::EVIDENCIA_LECTURA_API,
            TiendanubePrecioConciliacion::EVIDENCIA_EXPORT_DECLARADO,
            TiendanubePrecioConciliacion::EVIDENCIA_MANUAL_DECLARADO,
        ], true)) {
            throw new TiendanubePrecioLoteException('El tipo de evidencia no es válido.', 'validacion');
        }

        $varianteIds = array_values(array_unique(array_map('intval', $datos['variante_ids'] ?? [])));
        $items = $revision->items()->when(
            $varianteIds !== [],
            fn ($q) => $q->whereIn('variante_id', $varianteIds)
        )->get();

        if ($items->isEmpty()) {
            throw new TiendanubePrecioLoteException('No hay filas para conciliar.', 'validacion');
        }

        $filas = match ($tipo) {
            TiendanubePrecioConciliacion::EVIDENCIA_LECTURA_API => $this->conciliarPorApi($lote, $items),
            TiendanubePrecioConciliacion::EVIDENCIA_EXPORT_DECLARADO => $this->conciliarPorExport($lote, $items, $datos),
            default => $this->conciliarManual($items),
        };

        DB::transaction(function () use ($lote, $revision, $userId, $filas, $tipo) {
            foreach ($filas as $fila) {
                TiendanubePrecioConciliacion::query()->create($fila + [
                    'lote_id' => $lote->id,
                    'revision_id' => $revision->id,
                    'actor_id' => $userId,
                    'evidencia_tipo' => $tipo,
                    'evidencia_at' => now(),
                ]);
            }
            $this->lotes->registrarEvento($lote, $revision, TiendanubePrecioLoteEvento::TIPO_CONCILIACION_REGISTRADA, $userId, [
                'evidencia_tipo' => $tipo,
                'filas' => count($filas),
            ]);
        });

        $serializadas = array_map(
            fn (array $fila) => $this->ocultarCostoSiAplica($fila, $puedeVerCosto),
            $filas
        );

        return [
            'lote_id' => $lote->id,
            'evidencia_tipo' => $tipo,
            'filas' => $serializadas,
        ];
    }

    /**
     * @param  Collection<int, TiendanubePrecioLoteItem>  $items
     * @return list<array<string, mixed>>
     */
    private function conciliarPorApi(TiendanubePrecioLote $lote, $items): array
    {
        $cache = [];
        $filas = [];
        foreach ($items as $item) {
            $espejo = TiendanubeProductoVariante::query()->find((int) $item->variante_id);
            if (! $espejo || (int) $espejo->producto_id !== (int) $item->producto_id) {
                foreach (TiendanubePrecioDestino::cases() as $destino) {
                    $filas[] = $this->filaItem(
                        $item,
                        $destino->value,
                        $this->valorOperacion($item, $destino->value),
                        null,
                        TiendanubePrecioConciliacion::RESULTADO_NO_RESTAURABLE,
                        'El producto o la variante ya no está en el espejo de esta tienda. No se sustituye por otro SKU.'
                    );
                }

                continue;
            }

            $productoId = (int) $item->producto_id;
            if (! isset($cache[$productoId])) {
                $consulta = $this->api->consultarProducto($productoId);
                $cache[$productoId] = $consulta;
            }
            $consulta = $cache[$productoId];
            if ($consulta['estado'] !== 'existe' || ! is_array($consulta['recurso'])) {
                foreach (TiendanubePrecioDestino::cases() as $destino) {
                    $filas[] = $this->filaItem(
                        $item,
                        $destino->value,
                        $this->valorOperacion($item, $destino->value),
                        null,
                        TiendanubePrecioConciliacion::RESULTADO_NO_RESTAURABLE,
                        $consulta['estado'] === 'ausente'
                            ? 'El producto desapareció en la tienda. Se conserva el nombre histórico y no se restaura.'
                            : 'No se pudo leer el producto remoto. Intente de nuevo.'
                    );
                }

                continue;
            }

            $variante = $this->control->varianteDeProducto($consulta['recurso'], (int) $item->variante_id);
            if (! $variante) {
                foreach (TiendanubePrecioDestino::cases() as $destino) {
                    $filas[] = $this->filaItem(
                        $item,
                        $destino->value,
                        $this->valorOperacion($item, $destino->value),
                        null,
                        TiendanubePrecioConciliacion::RESULTADO_NO_RESTAURABLE,
                        'La variante histórica ya no existe en el producto remoto.'
                    );
                }

                continue;
            }

            $remoto = $this->control->preciosDeVariante($variante);
            foreach (TiendanubePrecioDestino::cases() as $destino) {
                $clave = $destino->value;
                $operacion = $this->control->normalizar($this->valorOperacion($item, $clave));
                $valorRemoto = $remoto[$clave] ?? null;
                $igual = $this->control->iguales($operacion, $valorRemoto);
                $filas[] = $this->filaItem(
                    $item,
                    $clave,
                    $operacion,
                    $valorRemoto,
                    $igual ? TiendanubePrecioConciliacion::RESULTADO_COINCIDE : TiendanubePrecioConciliacion::RESULTADO_DIFIERE,
                    $igual
                        ? 'El valor remoto coincide con el de la operación. Un movimiento de inventario o imagen no se considera conflicto de precio.'
                        : 'El valor monetario remoto difiere del registrado en la operación.'
                );
            }
        }

        return $filas;
    }

    /**
     * @param  Collection<int, TiendanubePrecioLoteItem>  $items
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    private function conciliarPorExport(TiendanubePrecioLote $lote, $items, array $datos): array
    {
        $artefactoId = (int) ($datos['artefacto_id'] ?? 0);
        $query = TiendanubePrecioCsvArtefacto::query()->where('lote_id', $lote->id);
        $artefacto = $artefactoId > 0
            ? $query->where('id', $artefactoId)->first()
            : $query->whereNotNull('importacion_declarada_at')->orderByDesc('id')->first();

        if (! $artefacto) {
            throw new TiendanubePrecioLoteException(
                'No hay una importación declarada vinculada a esta operación.',
                'evidencia_insuficiente'
            );
        }

        $filas = [];
        foreach ($items as $item) {
            foreach (TiendanubePrecioDestino::cases() as $destino) {
                $operacion = $this->control->normalizar($this->valorOperacion($item, $destino->value));
                $filas[] = $this->filaItem(
                    $item,
                    $destino->value,
                    $operacion,
                    null,
                    TiendanubePrecioConciliacion::RESULTADO_COINCIDE,
                    'Evidencia del archivo exportado el '.$artefacto->generado_at?->toIso8601String().'. No verifica el estado actual de la tienda.',
                    $operacion
                );
            }
        }

        return $filas;
    }

    /**
     * @param  Collection<int, TiendanubePrecioLoteItem>  $items
     * @return list<array<string, mixed>>
     */
    private function conciliarManual($items): array
    {
        $filas = [];
        foreach ($items as $item) {
            foreach (TiendanubePrecioDestino::cases() as $destino) {
                $filas[] = $this->filaItem(
                    $item,
                    $destino->value,
                    $this->valorOperacion($item, $destino->value),
                    null,
                    TiendanubePrecioConciliacion::RESULTADO_DIFIERE,
                    'Informe manual sin evidencia remota. Queda declarado, no verificado.'
                );
            }
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaItem(
        TiendanubePrecioLoteItem $item,
        string $campo,
        ?string $operacion,
        ?string $remoto,
        string $resultado,
        string $explicacion,
        ?string $export = null
    ): array {
        return [
            'item_id' => $item->id,
            'variante_id' => (int) $item->variante_id,
            'campo' => $campo,
            'valor_operacion' => $operacion,
            'valor_remoto' => $remoto,
            'valor_export' => $export,
            'resultado' => $resultado,
            'explicacion' => $explicacion,
        ];
    }

    private function valorOperacion(TiendanubePrecioLoteItem $item, string $campo): ?string
    {
        $final = $item->resultado_final['campos'][$campo] ?? [];
        $intencion = $final['intencion'] ?? 'conservar';
        if ($intencion === 'eliminar') {
            return null;
        }
        if ($intencion !== 'conservar' && array_key_exists('valor_final', $final)) {
            return $final['valor_final'] !== null ? (string) $final['valor_final'] : null;
        }

        return isset(($item->valores_anteriores ?? [])[$campo])
            ? (string) $item->valores_anteriores[$campo]
            : null;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function ocultarCostoSiAplica(array $fila, bool $puedeVerCosto): array
    {
        if ($puedeVerCosto || ($fila['campo'] ?? '') !== TiendanubePrecioDestino::CostoRemoto->value) {
            return $fila;
        }
        $fila['valor_operacion'] = null;
        $fila['valor_remoto'] = null;
        $fila['valor_export'] = null;

        return $fila;
    }
}
