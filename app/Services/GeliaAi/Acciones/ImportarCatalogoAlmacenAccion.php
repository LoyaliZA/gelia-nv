<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\ResolverAlmacenGeliaAi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Base de importaciones de Almacenes (costos / inventarios) desde un adjunto de Gelia AI.
 * Reutiliza IniciarImportacionAlmacenService; el proceso real corre en ImportarAlmacenCatalogoJob.
 */
abstract class ImportarCatalogoAlmacenAccion implements AccionGeliaAi
{
    public function __construct(
        protected GeliaAiArchivoService $archivos,
        protected IniciarImportacionAlmacenService $importacion,
        protected ResolverAlmacenGeliaAi $almacenes,
    ) {}

    /** costos|inventarios (tipo de IniciarImportacionAlmacenService). */
    abstract protected function tipo(): string;

    abstract protected function etiqueta(): string;

    /** Kind de InspeccionarArchivoGeliaAiService esperado. */
    abstract protected function kindEsperado(): string;

    /** @return list<string> */
    abstract protected function columnasObligatorias(): array;

    /** @return list<string> */
    abstract protected function columnasOpcionales(): array;

    /** Al menos una de estas columnas debe estar mapeada (vacío = sin regla). */
    protected function columnasValor(): array
    {
        return [];
    }

    public function parametros(): array
    {
        $cols = [...$this->columnasObligatorias(), ...$this->columnasOpcionales()];

        return [
            'type' => 'object',
            'properties' => [
                'file_id' => ['type' => 'string', 'maxLength' => 200, 'description' => "file_id (UUID) del archivo de {$this->etiqueta()} entre los archivos disponibles"],
                'almacen' => ['type' => 'string', 'maxLength' => 60, 'description' => 'Código o nombre del almacén destino'],
                'mapping' => [
                    'type' => 'object',
                    'description' => 'Encabezado del archivo para cada campo. Omite los que la detección automática ya resolvió.',
                    'properties' => array_fill_keys($cols, ['type' => 'string', 'maxLength' => 120]),
                ],
            ],
        ];
    }

    public function preparar(User $user, array $args, array $archivos = []): array
    {
        $faltantes = [];
        $meta = $this->archivos->resolverReferencia($user, $args['file_id'] ?? null, $archivos, $this->kindEsperado());
        if (! $meta) {
            $faltantes[] = ['dato' => 'archivo', 'detalle' => "Archivo de {$this->etiqueta()} (CSV/XLSX) adjunto en el chat"];
        }

        $almacenRef = $args['almacen'] ?? $args['almacen_codigo'] ?? null;
        $almacenId = isset($args['almacen_id']) ? (int) $args['almacen_id'] : null;
        $almacen = null;
        if (! $almacenId && ($almacenRef === null || trim((string) $almacenRef) === '')) {
            $faltantes[] = ['dato' => 'almacen', 'detalle' => 'Almacén destino (código o nombre)'];
        } else {
            $res = $this->almacenes->resolverParaUsuario($user, $almacenId, $almacenRef !== null ? (string) $almacenRef : null);
            if (! $res['ok']) {
                return $res + ($faltantes ? ['faltantes' => $faltantes] : []);
            }
            $almacen = $res['almacen'];
        }

        if ($faltantes !== []) {
            return [
                'ok' => false,
                'error_code' => 'faltan_datos',
                'error' => "Faltan datos para preparar la importación de {$this->etiqueta()}.",
                'faltantes' => $faltantes,
            ];
        }

        $permitidas = [...$this->columnasObligatorias(), ...$this->columnasOpcionales()];
        $headers = array_values(array_map('strval', $meta['headers'] ?? []));
        $guess = array_intersect_key(is_array($meta['guess_mapping'] ?? null) ? $meta['guess_mapping'] : [], array_flip($permitidas));
        $pedido = array_intersect_key(is_array($args['mapping'] ?? null) ? $args['mapping'] : [], array_flip($permitidas));
        $mapping = array_filter(array_map('strval', array_merge($guess, $pedido)), fn ($v) => trim($v) !== '');

        $sinMapear = array_values(array_diff($this->columnasObligatorias(), array_keys($mapping)));
        if ($sinMapear !== []) {
            return [
                'ok' => false,
                'error_code' => 'mapping_incompleto',
                'error' => 'No se pudieron identificar estas columnas: '.implode(', ', $sinMapear).'. Pide a la persona usuaria qué encabezado corresponde.',
                'faltantes' => array_map(fn ($c) => ['dato' => "mapping.{$c}", 'detalle' => "Columna del archivo para {$c}"], $sinMapear),
                'opciones' => $headers,
            ];
        }

        if ($headers !== []) {
            $inexistentes = array_keys(array_filter($mapping, fn ($col) => ! in_array($col, $headers, true)));
            if ($inexistentes !== []) {
                return [
                    'ok' => false,
                    'error_code' => 'columna_inexistente',
                    'error' => 'El mapping usa columnas que no existen en el archivo: '.implode(', ', $inexistentes).'.',
                    'opciones' => $headers,
                ];
            }
        }

        if ($this->columnasValor() !== [] && array_intersect($this->columnasValor(), array_keys($mapping)) === []) {
            return [
                'ok' => false,
                'error_code' => 'mapping_incompleto',
                'error' => 'Falta mapear al menos una columna de valor: '.implode(', ', $this->columnasValor()).'.',
                'opciones' => $headers,
            ];
        }

        $advertencias = [];
        $kind = (string) ($meta['kind'] ?? 'desconocido');
        if (! in_array($kind, [$this->kindEsperado(), 'desconocido'], true)) {
            $advertencias[] = "El archivo parece de tipo «{$kind}», no de {$this->etiqueta()}. Verifica antes de confirmar.";
        }

        $columnas = implode(', ', array_map(fn ($k, $v) => "{$k}=«{$v}»", array_keys($mapping), $mapping));
        $filas = (int) ($meta['rows'] ?? 0);

        return [
            'ok' => true,
            'payload' => [
                'file_id' => (string) $meta['file_id'],
                'almacen_id' => (int) $almacen->id,
                'mapping' => $mapping,
            ],
            'resumen' => "Importar {$this->etiqueta()} desde «{$meta['original_name']}»"
                .($filas > 0 ? " (≈{$filas} filas)" : '')
                ." al almacén {$almacen->codigo} · {$almacen->nombre}. Columnas: {$columnas}.",
            'advertencias' => $advertencias,
            'detalle' => [
                'operacion' => 'Importar '.$this->etiqueta(),
                'almacen' => "{$almacen->codigo} · {$almacen->nombre}",
                'archivos' => [array_filter([
                    'nombre' => (string) $meta['original_name'],
                    'filas' => $filas > 0 ? $filas : null,
                ])],
                'columnas' => array_map(
                    fn ($campo, $col) => ['campo' => str_replace('_', ' ', $campo), 'columna' => $col],
                    array_keys($mapping),
                    $mapping,
                ),
                'efecto' => 'Se enviará a la cola de importaciones de Almacenes. El resultado por fila se revisa en Almacenes → Importaciones.',
                'confirmar' => 'Confirmar importación',
            ],
        ];
    }

    public function ejecutar(User $user, array $payload): array
    {
        $prep = $this->preparar($user, $payload);
        if (! $prep['ok']) {
            throw new RuntimeException($prep['error']);
        }

        $p = $prep['payload'];
        $meta = $this->archivos->metaDeUsuario($user, $p['file_id']);
        $origen = $this->archivos->rutaRelativa($user, $p['file_id']);
        $ext = strtolower(pathinfo($origen, PATHINFO_EXTENSION) ?: 'csv');

        // IniciarImportacionAlmacenService solo acepta rutas del área de importaciones del usuario;
        // se copia para conservar el adjunto en el chat.
        $destino = "importaciones/{$user->id}/".Str::uuid().'.'.$ext;
        Storage::makeDirectory(dirname($destino));
        Storage::copy($origen, $destino);

        $result = $this->importacion->ejecutarDesdeRuta(
            $user->id,
            $this->tipo(),
            $destino,
            $p['mapping'],
            $p['almacen_id'],
        );

        $almacen = $this->almacenes->resolver($p['almacen_id'], null);

        return [
            'ok' => true,
            'accion' => $this->id(),
            'reporte' => [
                'resumen' => "Importación de {$this->etiqueta()} en cola para {$almacen?->codigo} (archivo «".($meta['original_name'] ?? '')."»). El resultado final se revisa en Almacenes → Importaciones.",
                'estado' => 'en_cola',
                'detalles' => [],
                'log_id' => $result['log_id'],
                'download_url' => null,
            ],
        ];
    }
}
