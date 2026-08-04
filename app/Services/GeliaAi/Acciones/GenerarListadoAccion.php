<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\GenerarListadoDesdeRutasService;
use RuntimeException;

class GenerarListadoAccion implements AccionGeliaAi
{
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

    public function proponerSchema(): array
    {
        return [
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'tipo_lista' => [
                        'type' => 'string',
                        'description' => 'resurtido|costos|actualizada|inventario|venta_especial|meli|id numérico',
                    ],
                    'file_ids' => [
                        'type' => 'object',
                        'properties' => [
                            'existencias' => ['type' => 'string'],
                            'precios' => ['type' => 'string'],
                            'costos' => ['type' => 'string'],
                        ],
                        'required' => ['existencias'],
                    ],
                ],
                'required' => ['tipo_lista', 'file_ids'],
            ],
        ];
    }

    public function ejecutar(User $user, array $payload): array
    {
        $tipoLista = $payload['tipo_lista'] ?? 'resurtido';
        $tipoLista = $this->generador->normalizarTipoLista($tipoLista);
        $fileIds = is_array($payload['file_ids'] ?? null) ? $payload['file_ids'] : [];

        $existenciasId = (string) ($fileIds['existencias'] ?? $payload['file_id_existencias'] ?? '');
        if ($existenciasId === '') {
            // Heurística: primer file_id suelto o por kind
            $existenciasId = (string) ($payload['file_id'] ?? '');
        }
        if ($existenciasId === '') {
            throw new RuntimeException('Falta file_ids.existencias.');
        }

        $rutas = [
            'existencias' => $this->archivos->rutaAbsoluta($user, $existenciasId),
        ];

        foreach (['precios', 'costos'] as $rol) {
            $id = (string) ($fileIds[$rol] ?? '');
            if ($id !== '') {
                $rutas[$rol] = $this->archivos->rutaAbsoluta($user, $id);
            }
        }

        $result = $this->generador->generar($tipoLista, $rutas);
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
                'resumen' => 'Listado '. (is_scalar($tipoLista) ? (string) $tipoLista : '')." generado ({$result['filas']} filas)".(count($errores) ? '. Hay inconsistencias de precio.' : '.'),
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
}
