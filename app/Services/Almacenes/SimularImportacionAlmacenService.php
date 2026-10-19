<?php

namespace App\Services\Almacenes;

use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Support\Almacenes\OperacionesImportacionAlmacen;

class SimularImportacionAlmacenService
{
    public function __construct(
        private readonly LeerFilasImportacionAlmacenService $lector,
        private readonly ResolverRelacionCatalogoService $catalogo,
        private readonly ResolverProductoPorFolioImportacionService $folios,
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
            'detalle_filas' => [],
            'detalle_filas_omitidas' => 0,
            'altas_total' => 0,
            'skus_duplicados' => [],
        ];

        $skusVistos = [];
        $foliosVistos = [];
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
                $sku = $this->skuFila($row, $mapping);
                $folio = $this->folios->desdeFila($row, $mapping);

                try {
                    if ($folio) {
                        if (isset($foliosVistos[$folio])) {
                            throw new \RuntimeException("El folio {$folio} está repetido en el archivo. La primera fila es {$foliosVistos[$folio]}.");
                        }
                        $foliosVistos[$folio] = $numeroFila;
                    }
                    if ($sku !== '') {
                        $clave = mb_strtolower($sku);
                        if (isset($skusVistos[$clave])) {
                            $resumen['skus_duplicados'][] = [
                                'sku' => $sku,
                                'fila' => $numeroFila,
                                'primera_fila' => $skusVistos[$clave],
                            ];
                            throw new \RuntimeException("El SKU {$sku} está repetido en el archivo. La primera fila es {$skusVistos[$clave]}.");
                        }
                        $skusVistos[$clave] = $numeroFila;
                    }

                    $stats = $this->clasificarFila($row, $mapping, $almacenId, $operaciones, $contexto, $catalogoCache);

                    foreach (['productos_creados', 'productos_actualizados', 'asignaciones_creadas', 'costos_creados', 'costos_actualizados', 'cantidades_actualizadas', 'sin_cambios', 'ubicaciones_actualizadas'] as $k) {
                        $resumen[$k] = ($resumen[$k] ?? 0) + (int) ($stats[$k] ?? 0);
                    }
                    if ((int) ($stats['productos_creados'] ?? 0) > 0) {
                        $resumen['altas_total']++;
                    }
                    $accion = (string) ($stats['accion_texto'] ?? 'Sin cambios');
                    if ($accion !== 'Sin cambios') {
                        $this->anotarMuestra($resumen, $numeroFila, $folio, $sku, $accion, (int) ($stats['productos_creados'] ?? 0) > 0);
                    }
                } catch (\Throwable $e) {
                    $resumen['errores']++;
                    if (count($resumen['detalle_errores']) < 100) {
                        $resumen['detalle_errores'][] = [
                            'fila' => $numeroFila,
                            'folio' => $folio ?: 'vacío',
                            'sku' => $sku !== '' ? $sku : '—',
                            'mensaje' => $e->getMessage(),
                        ];
                    }
                    $this->anotarMuestra($resumen, $numeroFila, $folio, $sku, $e->getMessage(), true);
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
        foreach ($filas as $item) {
            $row = $item['row'];
            $sku = $this->skuFila($row, $mapping);
            if ($sku !== '') {
                $skus[$sku] = true;
            }
            $folio = $this->folios->desdeFila($row, $mapping);
            if ($folio) {
                $folios[$folio] = true;
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
            'ubicaciones_actualizadas' => 0,
            'sin_cambios' => 0,
            'accion_texto' => 'Sin cambios',
        ];
        $hubioCambio = false;
        $sku = $this->skuFila($row, $mapping);
        $folio = $this->folios->desdeFila($row, $mapping);
        $creadoEnFila = false;
        $producto = null;
        $acciones = [];

        if (in_array(OperacionesImportacionAlmacen::FICHA_PRODUCTO, $operaciones, true)) {
            $this->exigirCatalogo($row, $mapping, $catalogoCache);
            $producto = ($folio && isset($contexto['porFolio'][$folio])) ? $contexto['porFolio'][$folio] : null;
            if ($producto) {
                if ($sku !== '' && $sku !== $producto['sku']) {
                    $otro = $contexto['porSku'][mb_strtolower($sku)] ?? null;
                    if ($otro && $otro['id'] !== $producto['id']) {
                        throw new \RuntimeException("El SKU {$sku} ya pertenece a otro producto.");
                    }
                }
                if ($this->filaTieneDatosDeFicha($row, $mapping, $sku)) {
                    $stats['productos_actualizados']++;
                    $hubioCambio = true;
                    $acciones[] = 'Actualizar ficha';
                }
            } else {
                if ($sku === '') {
                    throw new \RuntimeException('SKU obligatorio.');
                }
                if (! $this->celdaConValor($row, $mapping['descripcion'] ?? null)) {
                    throw new \RuntimeException('Descripción obligatoria.');
                }
                $otro = $contexto['porSku'][mb_strtolower($sku)] ?? null;
                if ($otro) {
                    throw new \RuntimeException("El SKU {$sku} ya pertenece a otro producto.");
                }
                $stats['productos_creados']++;
                $creadoEnFila = true;
                $hubioCambio = true;
                $acciones[] = 'Alta';
            }
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::ASIGNACION_ALMACEN, $operaciones, true)) {
            if (! $producto && ! $creadoEnFila) {
                $producto = $this->productoPorFolioEnContexto($folio, $contexto);
            }
            $id = (int) ($producto['id'] ?? 0);
            if ($creadoEnFila || ! array_key_exists($id, $contexto['asignaciones'])) {
                $stats['asignaciones_creadas']++;
                $hubioCambio = true;
                $acciones[] = 'Vincular';
            } elseif ($this->celdaConValor($row, $mapping['ubicacion'] ?? null)) {
                $ubicacion = trim((string) $row[$mapping['ubicacion']]);
                $actual = (string) ($contexto['asignaciones'][$id] ?? '');
                if ($actual !== $ubicacion) {
                    $stats['ubicaciones_actualizadas']++;
                    $hubioCambio = true;
                    $acciones[] = 'Actualizar ubicación';
                }
            }
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::COSTOS_PRECIOS, $operaciones, true)) {
            if ($this->filaTieneDatosDeCosto($row, $mapping)) {
                if (! $producto && ! $creadoEnFila) {
                    $producto = $this->productoPorFolioEnContexto($folio, $contexto);
                }
                $id = (int) ($producto['id'] ?? 0);
                if ($creadoEnFila || ! isset($contexto['costos'][$id])) {
                    $stats['costos_creados']++;
                } else {
                    $stats['costos_actualizados']++;
                }
                $hubioCambio = true;
                $acciones[] = 'Costo';
            } elseif ($operaciones === [OperacionesImportacionAlmacen::COSTOS_PRECIOS]) {
                throw new \RuntimeException('Debe indicar al menos costo, costo de reposición o precio de venta.');
            }
        }

        if ($almacenId && in_array(OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA, $operaciones, true) && ! empty($mapping['existencia'])) {
            $valorExistencia = trim((string) ($row[$mapping['existencia']] ?? ''));
            if ($valorExistencia === '') {
                if ($operaciones === [OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA]) {
                    throw new \RuntimeException('Existencia obligatoria para cantidades de referencia.');
                }
            } else {
                if (! $producto && ! $creadoEnFila) {
                    $producto = $this->productoPorFolioEnContexto($folio, $contexto);
                }
                $existencia = (float) $valorExistencia;
                $actual = $producto ? ($contexto['inventarios'][$producto['id']] ?? null) : null;
                if ($creadoEnFila || $actual === null || (float) $actual !== $existencia) {
                    $stats['cantidades_actualizadas']++;
                    $hubioCambio = true;
                    $acciones[] = 'Existencia';
                }
            }
        }

        if (! $hubioCambio && $stats['sin_cambios'] === 0) {
            $stats['sin_cambios'] = 1;
        }

        $stats['accion_texto'] = $acciones === [] ? 'Sin cambios' : implode(', ', $acciones);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array{id: int, sku: string, folio: int}
     */
    private function productoPorFolioEnContexto(?int $folio, array $contexto): array
    {
        if ($folio === null) {
            throw new \RuntimeException('Folio obligatorio. Este caso no crea productos.');
        }

        $producto = $contexto['porFolio'][$folio] ?? null;
        if (! $producto) {
            throw new \RuntimeException("El folio {$folio} no existe. Este caso no crea productos.");
        }

        return $producto;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $mapping
     */
    private function filaTieneDatosDeFicha(array $row, array $mapping, string $sku): bool
    {
        if ($sku !== '') {
            return true;
        }

        foreach (['descripcion', 'marca', 'categoria', 'codigo_barras', 'peso', 'activo'] as $campo) {
            if ($this->celdaConValor($row, $mapping[$campo] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function celdaConValor(array $row, mixed $columna): bool
    {
        if (! is_string($columna) || $columna === '' || ! array_key_exists($columna, $row)) {
            return false;
        }

        return trim((string) $row[$columna]) !== '';
    }

    /**
     * @param  array<string, mixed>  $resumen
     */
    private function anotarMuestra(array &$resumen, int $fila, ?int $folio, string $sku, string $accion, bool $prioritaria): void
    {
        if (! $prioritaria && count($resumen['detalle_filas']) >= 40) {
            $resumen['detalle_filas_omitidas']++;

            return;
        }

        if (count($resumen['detalle_filas']) >= 80) {
            $resumen['detalle_filas_omitidas']++;

            return;
        }

        $resumen['detalle_filas'][] = [
            'fila' => $fila,
            'folio' => $folio ?: 'vacío',
            'sku' => $sku !== '' ? $sku : '—',
            'accion' => $accion,
        ];
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
