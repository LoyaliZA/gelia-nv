<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\CustomList;
use App\Models\User;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\GenerarListadoDesdeRutasService;
use RuntimeException;

class GenerarListadoAccion implements AccionGeliaAi
{
    /** Columnas derivadas de PG: sin archivo de precios salen en 0. */
    private const TIPOS_CON_PG = ['resurtido', 'actualizada', 'inventario', 'venta_especial', 'meli'];

    private const ROLES = ['existencias', 'precios', 'costos'];

    public function __construct(
        private GeliaAiArchivoService $archivos,
        private GenerarListadoDesdeRutasService $generador,
    ) {}

    public function id(): string
    {
        return 'generar_listado';
    }

    public function permiso(): string
    {
        return 'listados.ver';
    }

    public function descripcion(): string
    {
        return 'Prepara (no ejecuta) la generación de un listado Excel con el motor de Listados a partir de archivos adjuntos. Requiere archivo de existencias; precios/costos según el tipo. La persona usuaria confirma en la interfaz y el enlace de descarga solo existe tras generarse.';
    }

    public function parametros(): array
    {
        $tipos = array_keys(GenerarListadoDesdeRutasService::COLUMNAS_DEFAULT);

        return [
            'type' => 'object',
            'properties' => [
                'tipo_lista' => [
                    'type' => 'string',
                    'maxLength' => 60,
                    'description' => 'Tipo de lista: '.implode(', ', $tipos).', o el id numérico de una plantilla personalizada',
                ],
                'file_id_existencias' => ['type' => 'string', 'maxLength' => 200, 'description' => 'file_id del archivo de existencias'],
                'file_id_precios' => ['type' => 'string', 'maxLength' => 200, 'description' => 'file_id del archivo de precios (opcional)'],
                'file_id_costos' => ['type' => 'string', 'maxLength' => 200, 'description' => 'file_id del archivo de costos (opcional)'],
            ],
        ];
    }

    public function preparar(User $user, array $args, array $archivos = []): array
    {
        $tipoRaw = $args['tipo_lista'] ?? null;
        if ($tipoRaw === null || trim((string) $tipoRaw) === '') {
            return [
                'ok' => false,
                'error_code' => 'faltan_datos',
                'error' => 'Falta el tipo de listado.',
                'faltantes' => [['dato' => 'tipo_lista', 'detalle' => 'Tipo de listado a generar']],
                'opciones' => $this->opcionesTipo(),
            ];
        }

        $tipo = $this->generador->normalizarTipoLista(is_numeric($tipoRaw) ? (int) $tipoRaw : (string) $tipoRaw);
        $plantilla = null;
        if (is_int($tipo)) {
            $plantilla = CustomList::find($tipo);
            if (! $plantilla) {
                return ['ok' => false, 'error_code' => 'tipo_invalido', 'error' => "No existe la plantilla de listado {$tipo}.", 'opciones' => $this->opcionesTipo()];
            }
        } elseif (! isset(GenerarListadoDesdeRutasService::COLUMNAS_DEFAULT[$tipo])) {
            return ['ok' => false, 'error_code' => 'tipo_invalido', 'error' => "Tipo de listado no reconocido: {$tipo}.", 'opciones' => $this->opcionesTipo()];
        }

        $legacy = is_array($args['file_ids'] ?? null) ? $args['file_ids'] : [];
        $metas = [];
        foreach (self::ROLES as $rol) {
            $ref = $args["file_id_{$rol}"] ?? $legacy[$rol] ?? ($rol === 'existencias' ? ($args['file_id'] ?? null) : null);
            $meta = $this->archivos->resolverReferencia($user, $ref, $archivos, $rol);
            if ($meta) {
                $metas[$rol] = $meta;
            } elseif ($ref !== null && $ref !== '') {
                return ['ok' => false, 'error_code' => 'archivo_no_disponible', 'error' => "El archivo indicado para {$rol} no está disponible (no existe, expiró o no te pertenece)."];
            }
        }

        $requeridos = ['existencias', ...array_values(array_intersect((array) ($plantilla?->archivos_requeridos ?? []), ['precios', 'costos']))];
        $faltan = array_values(array_diff(array_unique($requeridos), array_keys($metas)));
        if ($faltan !== []) {
            return [
                'ok' => false,
                'error_code' => 'faltan_datos',
                'error' => 'Faltan archivos para generar el listado.',
                'faltantes' => array_map(fn ($r) => ['dato' => "archivo_{$r}", 'detalle' => "Archivo de {$r} (CSV/XLSX) adjunto en el chat"], $faltan),
            ];
        }

        $advertencias = [];
        if (is_string($tipo) && in_array($tipo, self::TIPOS_CON_PG, true) && ! isset($metas['precios'])) {
            $advertencias[] = 'Sin archivo de precios: PG y las listas derivadas saldrán en 0.';
        }
        if ($tipo === 'costos' && ! isset($metas['costos'])) {
            $advertencias[] = 'Sin archivo de costos: la columna Costo (Wizerp) saldrá en 0.';
        }
        foreach ($metas as $rol => $meta) {
            $kind = (string) ($meta['kind'] ?? 'desconocido');
            if (! in_array($kind, [$rol, 'desconocido'], true)) {
                $advertencias[] = "«{$meta['original_name']}» parece archivo de {$kind}, no de {$rol}.";
            }
        }

        $nombreTipo = $plantilla ? "plantilla «{$plantilla->titulo_lista}»" : "«{$tipo}»";
        $archivosTxt = implode(', ', array_map(fn ($r, $m) => "{$r}=«{$m['original_name']}»", array_keys($metas), $metas));

        return [
            'ok' => true,
            'payload' => [
                'tipo_lista' => $tipo,
                'file_ids' => array_map(fn ($m) => (string) $m['file_id'], $metas),
            ],
            'resumen' => "Generar listado {$nombreTipo} con {$archivosTxt}.",
            'advertencias' => $advertencias,
        ];
    }

    public function ejecutar(User $user, array $payload): array
    {
        $prep = $this->preparar($user, $payload);
        if (! $prep['ok']) {
            throw new RuntimeException($prep['error']);
        }

        $p = $prep['payload'];
        $rutas = [];
        foreach ($p['file_ids'] as $rol => $fileId) {
            $rutas[$rol] = $this->archivos->rutaAbsoluta($user, $fileId);
        }

        $result = $this->generador->generar($p['tipo_lista'], $rutas);
        $errores = array_map(
            fn ($row) => [
                'sku' => (string) ($row['sku'] ?? ''),
                'error' => 'Sin precio PG con existencia > 0',
            ],
            $result['inconsistencias'],
        );

        return [
            'ok' => true,
            'accion' => $this->id(),
            'reporte' => [
                'resumen' => "Listado {$p['tipo_lista']} generado: «{$result['nombre_descarga']}» ({$result['filas']} filas)"
                    .($errores ? '. Hay inconsistencias de precio.' : '.'),
                'estado' => 'completado',
                'detalles' => $errores,
                'conteos' => [
                    'ok' => $result['filas'],
                    'error' => count($result['inconsistencias']),
                ],
                'log_id' => null,
                'download_url' => route('listados.descargar_temporal', [
                    'temp_file' => $result['temp_file'],
                    'nombre_descarga' => $result['nombre_descarga'],
                ]),
                'temp_file' => $result['temp_file'],
                'nombre_descarga' => $result['nombre_descarga'],
            ],
        ];
    }

    /** @return list<array{tipo: string|int, nombre: string}> */
    private function opcionesTipo(): array
    {
        $sistema = array_map(fn ($t) => ['tipo' => $t, 'nombre' => $t], array_keys(GenerarListadoDesdeRutasService::COLUMNAS_DEFAULT));
        $custom = CustomList::query()->where('active', true)->orderBy('titulo_lista')->limit(20)->get(['id', 'titulo_lista'])
            ->map(fn (CustomList $c) => ['tipo' => (int) $c->id, 'nombre' => (string) $c->titulo_lista])->all();

        return [...$sistema, ...$custom];
    }
}
