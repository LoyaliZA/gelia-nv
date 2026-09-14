<?php

namespace App\Services\Tiendanube\Precios;

use App\Exceptions\Tiendanube\TiendanubePrecioFuenteException;
use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubePrecioImport;
use App\Models\Tiendanube\TiendanubePrecioImportItem;
use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

class TiendanubePrecioImportService
{
    public const MAX_BYTES = 2_097_152;

    public const MAX_FILAS = 5000;

    public function __construct(
        private TiendanubePrecioVarianteResolverService $variantes,
        private TiendanubePrecioCostoCapturaService $captura,
    ) {}

    /**
     * @param  array{
     *     identificador: string,
     *     columna_identificador: string,
     *     columnas_importes: array<string, string>,
     *     columna_moneda?: string|null,
     *     moneda_fija?: string|null
     * }  $mapeo
     */
    public function previsualizar(
        UploadedFile $archivo,
        int $storeId,
        string $delimiter,
        string $decimalSep,
        array $mapeo,
        ?User $user = null,
    ): TiendanubePrecioImport {
        $this->assertLimitesArchivo($archivo);
        $delimiter = $this->normalizarDelimitador($delimiter);
        $decimalSep = $decimalSep === ',' ? ',' : '.';
        $mapeo = $this->validarMapeo($mapeo);

        $import = TiendanubePrecioImport::create([
            'user_id' => $user?->id,
            'store_id' => $storeId,
            'estado' => TiendanubePrecioImport::ESTADO_REVISION,
            'delimiter' => $delimiter,
            'decimal_sep' => $decimalSep,
            'mapeo_json' => $mapeo,
        ]);

        $dir = 'tiendanube/precio-imports/'.$import->id;
        Storage::disk('local')->makeDirectory($dir);
        $path = $archivo->storeAs($dir, 'upload.csv', 'local');
        $import->update(['archivo_path' => $path]);

        $absolute = Storage::disk('local')->path($path);
        $this->parsearArchivo($import, $absolute, $delimiter, $decimalSep, $mapeo, $storeId);

        return $import->fresh(['items']) ?? $import;
    }

    public function revision(TiendanubePrecioImport $import): TiendanubePrecioImport
    {
        return $import->load('items');
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function confirmar(TiendanubePrecioImport $import, array $itemIds, ?User $user = null): TiendanubePrecioImport
    {
        if (! in_array($import->estado, [TiendanubePrecioImport::ESTADO_REVISION], true)) {
            throw new TiendanubePrecioFuenteException('La importación ya no admite confirmación.', 'import_cerrado');
        }

        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

        return DB::transaction(function () use ($import, $itemIds, $user) {
            $items = TiendanubePrecioImportItem::query()
                ->where('import_id', $import->id)
                ->lockForUpdate()
                ->get();

            $seleccionados = $items->whereIn('id', $itemIds);
            $confirmados = 0;
            $excluidos = 0;

            foreach ($items as $item) {
                if (! $seleccionados->contains('id', $item->id)) {
                    $item->update([
                        'seleccionado' => false,
                        'estado' => $item->estado === TiendanubePrecioImportItem::ESTADO_VALIDO
                            ? TiendanubePrecioImportItem::ESTADO_EXCLUIDO
                            : $item->estado,
                    ]);
                    $excluidos++;

                    continue;
                }

                if ($item->estado !== TiendanubePrecioImportItem::ESTADO_VALIDO) {
                    $item->update(['seleccionado' => false]);
                    $excluidos++;

                    continue;
                }

                $this->captura->capturar(
                    (int) $import->store_id,
                    (int) $item->variante_id,
                    (string) $item->valor_decimal,
                    (string) $item->moneda,
                    'Importación CSV #'.$import->id,
                    $user,
                    (string) $item->destino_tipo,
                    [
                        'lista_id' => $item->lista_id,
                        'origen' => TiendanubePrecioFuenteVersion::ORIGEN_ARCHIVO,
                        'import_id' => $import->id,
                    ]
                );

                $item->update([
                    'seleccionado' => true,
                    'estado' => TiendanubePrecioImportItem::ESTADO_CONFIRMADO,
                ]);
                $confirmados++;
            }

            $import->update([
                'estado' => $confirmados > 0 && $excluidos > 0
                    ? TiendanubePrecioImport::ESTADO_CONFIRMADO_PARCIAL
                    : TiendanubePrecioImport::ESTADO_CONFIRMADO,
                'confirmado_at' => now(),
            ]);

            return $import->fresh(['items']) ?? $import;
        });
    }

    /**
     * @param  array<string, mixed>  $mapeo
     * @return array{
     *     identificador: string,
     *     columna_identificador: string,
     *     columnas_importes: array<string, string>,
     *     columna_moneda: string|null,
     *     moneda_fija: string
     * }
     */
    private function validarMapeo(array $mapeo): array
    {
        $identificador = $mapeo['identificador'] ?? 'sku';
        if (! in_array($identificador, ['sku', 'variante_id'], true)) {
            throw new TiendanubePrecioFuenteException('El identificador debe ser sku o variante_id.', 'mapeo_invalido');
        }

        $colId = trim((string) ($mapeo['columna_identificador'] ?? ''));
        if ($colId === '') {
            throw new TiendanubePrecioFuenteException('Indique la columna de SKU o ID de variante.', 'mapeo_invalido');
        }

        $columnas = $mapeo['columnas_importes'] ?? [];
        if (! is_array($columnas) || $columnas === []) {
            throw new TiendanubePrecioFuenteException('Indique al menos una columna de importe con destino explícito.', 'mapeo_invalido');
        }

        $monedaFija = strtoupper(trim((string) ($mapeo['moneda_fija'] ?? 'MXN')));
        if (preg_match('/^[A-Z]{3}$/', $monedaFija) !== 1) {
            throw new TiendanubePrecioFuenteException('La moneda fija no es válida.', 'moneda_invalida');
        }

        $colMoneda = isset($mapeo['columna_moneda']) && $mapeo['columna_moneda'] !== ''
            ? trim((string) $mapeo['columna_moneda'])
            : null;

        return [
            'identificador' => $identificador,
            'columna_identificador' => $colId,
            'columnas_importes' => $columnas,
            'columna_moneda' => $colMoneda,
            'moneda_fija' => $monedaFija,
        ];
    }

    /**
     * @param  array{
     *     identificador: string,
     *     columna_identificador: string,
     *     columnas_importes: array<string, string>,
     *     columna_moneda: string|null,
     *     moneda_fija: string
     * }  $mapeo
     */
    private function parsearArchivo(
        TiendanubePrecioImport $import,
        string $absolute,
        string $delimiter,
        string $decimalSep,
        array $mapeo,
        int $storeId,
    ): void {
        $file = new SplFileObject($absolute, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        $header = null;
        $fila = 0;
        $items = [];
        $clavesVistas = [];
        $validas = 0;
        $errores = 0;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null] || $row === false) {
                continue;
            }
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);
            if ($header === null) {
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]) ?? $row[0];
                }
                $header = $row;

                continue;
            }

            $fila++;
            if ($fila > self::MAX_FILAS) {
                throw new TiendanubePrecioFuenteException(
                    'El archivo supera el máximo de '.self::MAX_FILAS.' filas.',
                    'archivo_grande'
                );
            }

            $asoc = $this->asociar($header, $row);
            $destinos = $this->expandirDestinos($mapeo['columnas_importes'], $storeId);
            $identRaw = $asoc[$mapeo['columna_identificador']] ?? '';
            $moneda = $mapeo['columna_moneda']
                ? strtoupper(trim((string) ($asoc[$mapeo['columna_moneda']] ?? '')))
                : $mapeo['moneda_fija'];
            if ($moneda === '') {
                $moneda = $mapeo['moneda_fija'];
            }

            $resolucion = $this->resolverIdentificador($mapeo['identificador'], $identRaw);

            foreach ($destinos as $destino) {
                $valorRaw = $asoc[$destino['columna']] ?? '';
                $item = [
                    'import_id' => $import->id,
                    'fila' => $fila,
                    'sku' => $mapeo['identificador'] === 'sku' ? (string) $identRaw : ($resolucion['sku'] ?? null),
                    'variante_id' => $resolucion['variante_id'],
                    'producto_id' => $resolucion['producto_id'],
                    'destino_tipo' => $destino['tipo'],
                    'lista_id' => $destino['lista_id'],
                    'valor_raw' => $valorRaw === '' ? null : $valorRaw,
                    'valor_decimal' => null,
                    'moneda' => $moneda,
                    'estado' => TiendanubePrecioImportItem::ESTADO_ERROR,
                    'motivo' => null,
                    'valor_anterior' => null,
                    'moneda_anterior' => null,
                    'seleccionado' => false,
                    'candidatos_json' => json_encode($resolucion['candidatos']),
                    'mensaje' => null,
                ];

                if ($destino['error']) {
                    $item['motivo'] = $destino['error'];
                    $item['mensaje'] = $destino['mensaje'];
                    $errores++;
                    $items[] = $item;

                    continue;
                }

                if ($resolucion['estado'] === TiendanubePrecioVarianteResolverService::ESTADO_NO_ENCONTRADO) {
                    $item['motivo'] = 'no_encontrado';
                    $item['mensaje'] = 'No hay coincidencia para el identificador.';
                    $errores++;
                    $items[] = $item;

                    continue;
                }

                if ($resolucion['estado'] === TiendanubePrecioVarianteResolverService::ESTADO_AMBIGUO) {
                    $item['motivo'] = 'ambiguo';
                    $item['mensaje'] = 'La coincidencia es ambigua; indique el ID de variante.';
                    $errores++;
                    $items[] = $item;

                    continue;
                }

                $parsed = TiendanubePrecioImporte::parse((string) $valorRaw, $decimalSep);
                if (! $parsed['ok']) {
                    $item['motivo'] = $parsed['error'];
                    $item['mensaje'] = 'Importe rechazado: '.$parsed['error'];
                    $errores++;
                    $items[] = $item;

                    continue;
                }
                if ($parsed['valor'] === null) {
                    $item['motivo'] = 'sin_importe';
                    $item['mensaje'] = 'Sin importe en el destino indicado.';
                    $errores++;
                    $items[] = $item;

                    continue;
                }

                $claveFila = $resolucion['variante_id'].'|'.$destino['tipo'].'|'.(int) $destino['lista_id'];
                if (isset($clavesVistas[$claveFila])) {
                    $item['motivo'] = 'duplicado';
                    $item['mensaje'] = 'Fila duplicada para la misma variante y destino.';
                    $errores++;
                    $items[] = $item;

                    continue;
                }
                $clavesVistas[$claveFila] = $fila;

                if (preg_match('/^[A-Z]{3}$/', $moneda) !== 1) {
                    $item['motivo'] = 'moneda_invalida';
                    $item['mensaje'] = 'Moneda inválida.';
                    $errores++;
                    $items[] = $item;

                    continue;
                }

                $actual = $this->versionActual(
                    $storeId,
                    (int) $resolucion['variante_id'],
                    $destino['tipo'],
                    $destino['lista_id']
                );
                if ($actual && strtoupper((string) $actual->moneda) !== $moneda) {
                    $item['motivo'] = 'moneda_incompatible';
                    $item['mensaje'] = 'Moneda incompatible con la versión vigente ('.$actual->moneda.').';
                    $item['valor_anterior'] = $actual->valorDecimalString();
                    $item['moneda_anterior'] = $actual->moneda;
                    $errores++;
                    $items[] = $item;

                    continue;
                }

                $item['valor_decimal'] = $parsed['valor'];
                $item['estado'] = TiendanubePrecioImportItem::ESTADO_VALIDO;
                $item['valor_anterior'] = $actual?->valorDecimalString();
                $item['moneda_anterior'] = $actual?->moneda;
                $item['seleccionado'] = true;
                $validas++;
                $items[] = $item;
            }
        }

        $ahora = now();
        foreach (array_chunk($items, 200) as $chunk) {
            foreach ($chunk as &$filaItem) {
                $filaItem['created_at'] = $ahora;
                $filaItem['updated_at'] = $ahora;
            }
            unset($filaItem);
            TiendanubePrecioImportItem::insert($chunk);
        }

        $import->update([
            'total_filas' => $fila,
            'validas' => $validas,
            'errores' => $errores,
        ]);
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $row
     * @return array<string, string>
     */
    private function asociar(array $header, array $row): array
    {
        $out = [];
        foreach ($header as $i => $name) {
            $out[$name] = $row[$i] ?? '';
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $columnas
     * @return list<array{columna: string, tipo: string, lista_id: int|null, error: string|null, mensaje: string|null}>
     */
    private function expandirDestinos(array $columnas, int $storeId): array
    {
        $destinos = [];
        foreach ($columnas as $destino => $columna) {
            $columna = trim((string) $columna);
            $destino = trim((string) $destino);
            if ($columna === '' || $destino === '') {
                $destinos[] = [
                    'columna' => $columna,
                    'tipo' => TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
                    'lista_id' => null,
                    'error' => 'destino_ambiguo',
                    'mensaje' => 'Cada columna de importe necesita un destino explícito.',
                ];

                continue;
            }

            if ($destino === TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL || $destino === 'costo') {
                $destinos[] = [
                    'columna' => $columna,
                    'tipo' => TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
                    'lista_id' => null,
                    'error' => null,
                    'mensaje' => null,
                ];

                continue;
            }

            $listaId = null;
            if (str_starts_with($destino, 'lista:')) {
                $listaId = (int) substr($destino, 6);
            } elseif (ctype_digit($destino)) {
                $listaId = (int) $destino;
            }

            $lista = $listaId
                ? TiendanubePrecioLista::query()->where('store_id', $storeId)->find($listaId)
                : null;

            if (! $lista) {
                $destinos[] = [
                    'columna' => $columna,
                    'tipo' => TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
                    'lista_id' => $listaId,
                    'error' => 'lista_inexistente',
                    'mensaje' => 'Destino de lista desconocido.',
                ];

                continue;
            }

            $destinos[] = [
                'columna' => $columna,
                'tipo' => TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
                'lista_id' => (int) $lista->id,
                'error' => null,
                'mensaje' => null,
            ];
        }

        return $destinos;
    }

    /**
     * @return array{
     *     estado: string,
     *     sku: string,
     *     variante_id: int|null,
     *     producto_id: int|null,
     *     candidatos: list<array{variante_id: int, producto_id: int, sku: string|null, nombre: string}>
     * }
     */
    private function resolverIdentificador(string $tipo, string $raw): array
    {
        $raw = trim($raw);
        if ($tipo === 'variante_id') {
            if ($raw === '' || ! ctype_digit($raw)) {
                return [
                    'estado' => TiendanubePrecioVarianteResolverService::ESTADO_NO_ENCONTRADO,
                    'sku' => '',
                    'variante_id' => null,
                    'producto_id' => null,
                    'candidatos' => [],
                ];
            }

            return $this->variantes->resolverPorVarianteId((int) $raw);
        }

        return $this->variantes->resolverPorSku($raw);
    }

    private function versionActual(int $storeId, int $varianteId, string $tipo, ?int $listaId): ?TiendanubePrecioFuenteVersion
    {
        return TiendanubePrecioFuenteVersion::query()
            ->where('store_id', $storeId)
            ->where('fuente_clave', TiendanubePrecioFuenteVersion::claveFuente($tipo, $listaId))
            ->where('variante_id', $varianteId)
            ->orderByDesc('version')
            ->first();
    }

    private function assertLimitesArchivo(UploadedFile $archivo): void
    {
        if ($archivo->getSize() !== null && $archivo->getSize() > self::MAX_BYTES) {
            throw new TiendanubePrecioFuenteException('El archivo supera el tamaño máximo (2 MB).', 'archivo_grande');
        }
    }

    private function normalizarDelimitador(string $delimiter): string
    {
        return match ($delimiter) {
            ';', 'tab', '\t', "\t" => $delimiter === ';' ? ';' : "\t",
            default => ',',
        };
    }
}
