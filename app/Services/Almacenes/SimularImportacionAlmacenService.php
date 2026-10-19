<?php

namespace App\Services\Almacenes;

use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Support\Almacenes\OperacionesImportacionAlmacen;
use App\Support\Almacenes\ReglasFichaProductoImportacion;

class SimularImportacionAlmacenService
{
    public function __construct(
        private readonly LeerFilasImportacionAlmacenService $lector,
        private readonly ResolverRelacionCatalogoService $catalogo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(ImportacionAlmacenLog $log): array
    {
        $operaciones = $log->operaciones ?? [];
        if ($operaciones === []) {
            $operaciones = $this->operacionesDesdeTipoLegacy($log->tipo);
        }

        $mapping = $log->mapping ?? [];
        $almacenId = $log->almacen_id ? (int) $log->almacen_id : null;

        if (! $log->archivo_normalizado) {
            $normalizado = $this->lector->normalizarArchivo($log);
            $log->update([
                'archivo_normalizado' => $normalizado['path'],
                'total_filas' => $normalizado['total_filas'],
            ]);
            $log = $log->fresh();
        }

        $resumen = [
            'total_filas' => 0,
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'asignaciones_creadas' => 0,
            'costos_creados' => 0,
            'costos_actualizados' => 0,
            'cantidades_actualizadas' => 0,
            'sin_cambios' => 0,
            'errores' => 0,
            'detalle_errores' => [],
            'skus_duplicados' => [],
        ];

        $skusVistos = [];
        $offset = 0;
        $tamano = 500;
        $catalogoCache = [];

        while (true) {
            $lote = $this->lector->leerLote($log, $offset, $tamano);
            if ($lote['filas'] === []) {
                break;
            }

            $contexto = $this->contextoLote($lote['filas'], $mapping, $almacenId, $operaciones);

            foreach ($lote['filas'] as $item) {
                $resumen['total_filas']++;
                $numeroFila = $item['numero_fila'];
                $row = $item['row'];
                $sku = trim((string) ($row[$mapping['sku'] ?? ''] ?? ''));
                if ($sku !== '') {
                    $clave = mb_strtolower($sku);
                    if (isset($skusVistos[$clave])) {
                        $resumen['skus_duplicados'][] = [
                            'sku' => $sku,
                            'fila' => $numeroFila,
                            'primera_fila' => $skusVistos[$clave],
                        ];
                    } else {
                        $skusVistos[$clave] = $numeroFila;
                    }
                }

                try {
                    $stats = $this->clasificarFila($row, $mapping, $almacenId, $operaciones, $contexto, $catalogoCache);

                    foreach (['productos_creados', 'productos_actualizados', 'asignaciones_creadas', 'costos_creados', 'costos_actualizados', 'cantidades_actualizadas', 'sin_cambios'] as $k) {
                        $resumen[$k] += (int) ($stats[$k] ?? 0);
                    }
                } catch (\Throwable $e) {
                    $resumen['errores']++;
                    if (count($resumen['detalle_errores']) < 100) {
                        $resumen['detalle_errores'][] = [
                            'fila' => $numeroFila,
                            'sku' => $sku ?: '—',
                            'mensaje' => $e->getMessage(),
                        ];
                    }
                }
            }

            $offset += count($lote['filas']);
            if ($offset >= ($log->total_filas ?: $offset)) {
                break;
            }
        }

        $log->update([
            'estado' => 'simulado',
            'resumen_simulacion' => $resumen,
        ]);

        return $resumen;
    }

    /**
     * Cada fila se evalúa contra el estado actual. Lo creado en la simulación no se arrastra
     * a la fila siguiente: la simulación anterior revertía cada fila.
     *
     * @param  list<array{numero_fila: int, row: array<string, mixed>}>  $filas
     * @param  array<string, mixed>  $mapping
     * @param  list<string>  $operaciones
     * @return array{
     *   porSku: array<string, array{id: int, sku: string, folio: int}>,
     *   porFolio: array<int, array{id: int, sku: string, folio: int}>,
     *   inventarios: array<int, float>,
     *   costos: array<int, true>,
     *   asignaciones: array<int, ?string>
     * }
     */
    private function contextoLote(array $filas, array $mapping, ?int $almacenId, array $operaciones): array
    {
        $skus = [];
        $folios = [];
        $ficha = in_array(OperacionesImportacionAlmacen::FICHA_PRODUCTO, $operaciones, true);
        foreach ($filas as $item) {
            $row = $item['row'];
            $sku = $this->skuFila($row, $mapping);
            if ($sku !== '') {
                $skus[$sku] = true;
            }
            if ($ficha && ! empty($mapping['folio'])) {
                $folio = (int) preg_replace('/\D/', '', (string) ($row[$mapping['folio']] ?? ''));
                if ($folio > 0) {
                    $folios[$folio] = true;
                }
            }
        }

        $porSku = [];
        $porFolio = [];
        $ids = [];
        $productos = Producto::query()
            ->whereIn('sku', array_keys($skus))
            ->when($folios !== [], fn ($q) => $q->orWhereIn('folio', array_keys($folios)))
            ->get(['id', 'sku', 'folio']);

        foreach ($productos as $producto) {
            $dato = [
                'id' => (int) $producto->id,
                'sku' => (string) $producto->sku,
                'folio' => (int) $producto->folio,
            ];
            $porSku[mb_strtolower($dato['sku'])] = $dato;
            if ($dato['folio'] > 0) {
                $porFolio[$dato['folio']] = $dato;
            }
            $ids[] = $dato['id'];
        }

        $inventarios = [];
        $costos = [];
        $asignaciones = [];
        if ($almacenId && $ids !== []) {
            if (in_array(OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA, $operaciones, true)) {
                $inventarios = Inventario::query()
                    ->where('almacen_id', $almacenId)
                    ->whereIn('producto_id', $ids)
                    ->pluck('existencia', 'producto_id')
                    ->map(fn ($v) => (float) $v)
                    ->all();
            }
            if (in_array(OperacionesImportacionAlmacen::COSTOS_PRECIOS, $operaciones, true)) {
                $costos = ProductoCosto::query()
                    ->where('almacen_id', $almacenId)
                    ->whereIn('producto_id', $ids)
                    ->pluck('producto_id', 'producto_id')
                    ->map(fn () => true)
                    ->all();
            }
            if (in_array(OperacionesImportacionAlmacen::ASIGNACION_ALMACEN, $operaciones, true)) {
                $asignaciones = ProductoAlmacen::query()
                    ->where('almacen_id', $almacenId)
                    ->whereIn('producto_id', $ids)
                    ->pluck('ubicacion', 'producto_id')
                    ->all();
            }
        }

        return compact('porSku', 'porFolio', 'inventarios', 'costos', 'asignaciones');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     * @param  list<string>  $operaciones
     * @param  array<string, mixed>  $contexto
     * @param  array<string, int|null>  $catalogoCache
     * @return array<string, int>
     */
    private function clasificarFila(array $row, array $mapping, ?int $almacenId, array $operaciones, array $contexto, array &$catalogoCache): array
    {
        $stats = [
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'asignaciones_creadas' => 0,
            'costos_creados' => 0,
            'costos_actualizados' => 0,
            'cantidades_actualizadas' => 0,
            'sin_cambios' => 0,
        ];
        $hubioCambio = false;
        $sku = $this->skuFila($row, $mapping);
        $creadoEnFila = false;
        $producto = null;

        if (in_array(OperacionesImportacionAlmacen::FICHA_PRODUCTO, $operaciones, true)) {
            ReglasFichaProductoImportacion::asegurarValoresEnFila($row, $mapping);
            $this->exigirCatalogo($row, $mapping, $catalogoCache);
            $folio = (int) preg_replace('/\D/', '', (string) $row[$mapping['folio']]);
            $producto = $contexto['porFolio'][$folio] ?? $contexto['porSku'][mb_strtolower($sku)] ?? null;
            if ($producto && $folio > 0 && isset($contexto['porFolio'][$folio]) && $contexto['porFolio'][$folio]['id'] !== $producto['id']) {
                throw new \RuntimeException("El folio {$folio} ya está asignado a otro producto.");
            }
            if (! $producto && $folio > 0 && isset($contexto['porFolio'][$folio])) {
                throw new \RuntimeException("El folio {$folio} ya está asignado a otro producto.");
            }
            if ($producto && $producto['sku'] !== $sku) {
                $otro = $contexto['porSku'][mb_strtolower($sku)] ?? null;
                if ($otro && $otro['id'] !== $producto['id']) {
                    throw new \RuntimeException("El SKU {$sku} ya pertenece a otro producto.");
                }
            }
            if ($producto) {
                $stats['productos_actualizados']++;
            } else {
                $stats['productos_creados']++;
                $creadoEnFila = true;
            }
            $hubioCambio = true;
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::ASIGNACION_ALMACEN, $operaciones, true)) {
            if ($sku === '') {
                throw new \RuntimeException('SKU obligatorio.');
            }
            $producto ??= $contexto['porSku'][mb_strtolower($sku)] ?? null;
            if (! $producto && ! $creadoEnFila) {
                throw new \RuntimeException("Producto con SKU {$sku} no encontrado. Importa la ficha primero o incluye la operación de productos.");
            }
            if ($creadoEnFila || ! array_key_exists($producto['id'] ?? 0, $contexto['asignaciones'])) {
                $stats['asignaciones_creadas']++;
                $hubioCambio = true;
            }
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::COSTOS_PRECIOS, $operaciones, true) && $this->filaTieneDatosDeCosto($row, $mapping)) {
            if ($sku === '') {
                throw new \RuntimeException('SKU obligatorio.');
            }
            $producto ??= $contexto['porSku'][mb_strtolower($sku)] ?? null;
            if (! $producto && ! $creadoEnFila) {
                throw new \RuntimeException("Producto con SKU {$sku} no encontrado.");
            }
            if ($creadoEnFila || ! isset($contexto['costos'][$producto['id'] ?? 0])) {
                $stats['costos_creados']++;
            } else {
                $stats['costos_actualizados']++;
            }
            $hubioCambio = true;
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA, $operaciones, true) && ! empty($mapping['existencia'])) {
            if ($sku === '') {
                throw new \RuntimeException('SKU obligatorio.');
            }
            if (! isset($row[$mapping['existencia']]) || $row[$mapping['existencia']] === '') {
                throw new \RuntimeException('Existencia obligatoria para cantidades de referencia.');
            }
            $producto ??= $contexto['porSku'][mb_strtolower($sku)] ?? null;
            if (! $producto && ! $creadoEnFila) {
                throw new \RuntimeException("Producto con SKU {$sku} no encontrado.");
            }
            $existencia = (float) $row[$mapping['existencia']];
            $actual = $producto ? ($contexto['inventarios'][$producto['id']] ?? null) : null;
            if (! $creadoEnFila && $actual !== null && (float) $actual === $existencia) {
                $stats['sin_cambios']++;
            } else {
                $stats['cantidades_actualizadas']++;
                $hubioCambio = true;
            }
        }

        if (! $hubioCambio && $stats['sin_cambios'] === 0) {
            $stats['sin_cambios'] = 1;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     * @param  array<string, int|null>  $catalogoCache
     */
    private function exigirCatalogo(array $row, array $mapping, array &$catalogoCache): void
    {
        foreach (['categoria' => 'categoriaId', 'marca' => 'marcaId'] as $campo => $metodo) {
            if (empty($mapping[$campo]) || ! array_key_exists($mapping[$campo], $row) || trim((string) $row[$mapping[$campo]]) === '') {
                continue;
            }
            $texto = trim((string) $row[$mapping[$campo]]);
            $clave = $campo.':'.mb_strtoupper($texto);
            if (! array_key_exists($clave, $catalogoCache)) {
                $catalogoCache[$clave] = $this->catalogo->{$metodo}($texto);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function skuFila(array $row, array $mapping): string
    {
        $columna = $mapping['sku'] ?? '';
        if ($columna === '' || ! array_key_exists($columna, $row)) {
            return '';
        }

        return Producto::normalizarSku(trim((string) $row[$columna]));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function filaTieneDatosDeCosto(array $row, array $mapping): bool
    {
        foreach (['costo', 'costo_reposicion', 'precio_venta'] as $campo) {
            if (! empty($mapping[$campo]) && isset($row[$mapping[$campo]]) && $row[$mapping[$campo]] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function operacionesDesdeTipoLegacy(string $tipo): array
    {
        return match ($tipo) {
            'productos' => [OperacionesImportacionAlmacen::FICHA_PRODUCTO],
            'costos' => [OperacionesImportacionAlmacen::COSTOS_PRECIOS],
            'inventarios' => [
                OperacionesImportacionAlmacen::FICHA_PRODUCTO,
                OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA,
                OperacionesImportacionAlmacen::COSTOS_PRECIOS,
            ],
            default => [],
        };
    }
}
