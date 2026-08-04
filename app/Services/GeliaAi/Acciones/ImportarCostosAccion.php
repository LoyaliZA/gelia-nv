<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;
use App\Services\Almacenes\IniciarImportacionAlmacenService;
use App\Services\GeliaAi\GeliaAiArchivoService;
use App\Services\GeliaAi\ResolverAlmacenGeliaAi;
use RuntimeException;

class ImportarCostosAccion implements AccionGeliaAi
{
    public function __construct(
        private GeliaAiArchivoService $archivos,
        private IniciarImportacionAlmacenService $importacion,
        private ResolverAlmacenGeliaAi $almacenes,
    ) {}

    public function id(): string
    {
        return 'importar_costos';
    }

    public function permiso(): string
    {
        return 'almacenes.costos.importar';
    }

    public function proponerSchema(): array
    {
        return [
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'file_id' => ['type' => 'string'],
                    'almacen_codigo' => ['type' => 'string'],
                    'almacen_id' => ['type' => 'integer'],
                    'mapping' => ['type' => 'object'],
                ],
                'required' => ['file_id'],
            ],
        ];
    }

    public function ejecutar(User $user, array $payload): array
    {
        $fileId = (string) ($payload['file_id'] ?? '');
        if ($fileId === '') {
            throw new RuntimeException('Falta file_id.');
        }

        $meta = $this->archivos->metaDeUsuario($user, $fileId);
        if (! $meta) {
            throw new RuntimeException('Archivo no encontrado o expirado.');
        }

        $almacen = $this->almacenes->resolver(
            isset($payload['almacen_id']) ? (int) $payload['almacen_id'] : null,
            isset($payload['almacen_codigo']) ? (string) $payload['almacen_codigo'] : null,
        );
        if (! $almacen) {
            throw new RuntimeException('Indica un almacén válido (código o id).');
        }

        $mapping = is_array($payload['mapping'] ?? null) ? $payload['mapping'] : [];
        $guess = is_array($meta['guess_mapping'] ?? null) ? $meta['guess_mapping'] : [];
        $mapping = array_merge($guess, $mapping);
        if (empty($mapping['sku'])) {
            throw new RuntimeException('No se detectó columna SKU. Indica mapping.sku.');
        }

        $path = $this->archivos->rutaRelativa($user, $fileId);
        $result = $this->importacion->ejecutarDesdeRuta(
            $user->id,
            'costos',
            $path,
            $mapping,
            $almacen->id,
            conservarOrigen: true,
        );

        return [
            'ok' => true,
            'accion' => $this->id(),
            'reporte' => [
                'resumen' => "Importación de costos iniciada en {$almacen->codigo}. Seguimiento en Almacenes.",
                'detalles' => [],
                'conteos' => ['ok' => (int) ($meta['rows'] ?? 0), 'error' => 0],
                'log_id' => $result['log_id'],
                'download_url' => null,
            ],
        ];
    }
}
