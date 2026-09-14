<?php

namespace App\Services\Tiendanube\Precios\Exportacion;

use App\Exceptions\Tiendanube\TiendanubePrecioCsvException;
use App\Models\Tiendanube\TiendanubePrecioCsvArtefacto;
use App\Models\Tiendanube\TiendanubePrecioCsvPerfil;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteAprobacionService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use App\Support\Tiendanube\Precios\TiendanubePrecioCsvColumnaCatalogo;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use Illuminate\Support\Facades\Storage;

class ExportarPreciosCsvService
{
    public function __construct(
        private readonly TiendanubePrecioLoteAprobacionService $aprobacion,
        private readonly TiendanubePrecioLoteService $lotes,
        private readonly TiendanubePrecioCsvPerfilService $perfiles,
        private readonly TiendanubePrecioCsvFilaProductoService $filasProducto,
        private readonly TiendanubePrecioCsvSerializador $serializador,
        private readonly TiendanubePrecioCsvEscritor $escritor,
    ) {}

    /**
     * @param  list<string>|null  $columnas
     * @return array<string, mixed>
     */
    public function generarDesdeLote(
        string $loteId,
        int $storeId,
        int $userId,
        string $preset = TiendanubePrecioCsvColumnaCatalogo::PRESET_SOLO_PRECIOS,
        ?array $columnas = null
    ): array {
        $revision = $this->aprobacion->obtenerRevisionAprobada($loteId, $storeId, $userId);
        $columnasResueltas = $this->resolverColumnas($preset, $columnas);
        $perfil = $this->perfiles->exigirValidado($storeId);
        $hashColumnas = $this->hashColumnas($columnasResueltas);

        $existente = TiendanubePrecioCsvArtefacto::query()
            ->where('lote_id', $loteId)
            ->where('revision_checksum', $revision['checksum'])
            ->where('perfil_id', $perfil->id)
            ->where('columnas_hash', $hashColumnas)
            ->orderByDesc('id')
            ->first();
        if ($existente) {
            return $this->serializarArtefacto($existente);
        }

        $itemsExportables = [];
        foreach ($revision['items'] as $item) {
            if (empty($item['publicable']) || ! empty($item['excluido'])) {
                continue;
            }
            if (! $this->tieneCambioPrecio($item['campos'] ?? [])) {
                continue;
            }
            $itemsExportables[] = $item;
        }
        if ($itemsExportables === []) {
            throw new TiendanubePrecioCsvException(
                'No hay variantes publicables con cambios para exportar.',
                'sin_filas'
            );
        }

        $varianteIds = array_map(fn (array $item) => (int) $item['variante_id'], $itemsExportables);
        $espejo = $this->filasProducto->cargarVariantes($varianteIds);

        $filas = [];
        $conCambio = 0;
        foreach ($itemsExportables as $item) {
            $varianteId = (int) $item['variante_id'];
            $variante = $espejo[$varianteId] ?? null;
            if (! $variante) {
                throw new TiendanubePrecioCsvException(
                    'Una variante del lote ya no está en el catálogo local.',
                    'identidad_faltante'
                );
            }
            $mapa = $this->filasProducto->mapaDesdeEspejo($variante);
            $mapa = $this->serializador->aplicarIntenciones($mapa, $item['campos'] ?? []);
            $this->serializador->validarIdentidad($mapa);
            $filas[] = $this->proyeccion($mapa, $columnasResueltas);
            $conCambio++;
        }

        $artefacto = $this->persistir(
            $filas,
            $columnasResueltas,
            $preset,
            $perfil,
            $storeId,
            $userId,
            $loteId,
            (int) $revision['revision_id'],
            (string) $revision['checksum'],
            $conCambio,
            $hashColumnas
        );

        $lote = $this->lotes->obtenerAutorizado($loteId, $storeId, $userId);
        $this->lotes->registrarEvento(
            $lote,
            $lote->revisionActual(),
            TiendanubePrecioLoteEvento::TIPO_CSV_GENERADO,
            $userId,
            [
                'artefacto_id' => $artefacto->id,
                'hash' => $artefacto->hash_sha256,
                'perfil_version' => $artefacto->perfil_version,
                'revision_checksum' => $artefacto->revision_checksum,
                'filas' => $artefacto->filas_exportadas,
                'columnas' => $columnasResueltas,
            ]
        );

        return $this->serializarArtefacto($artefacto);
    }

    /**
     * @param  list<int>  $varianteIds
     * @param  list<string>|null  $columnas
     * @return array<string, mixed>
     */
    public function generarDesdeCatalogo(
        array $varianteIds,
        int $storeId,
        int $userId,
        string $preset = TiendanubePrecioCsvColumnaCatalogo::PRESET_PRODUCTO_COMPLETO,
        ?array $columnas = null
    ): array {
        $varianteIds = array_values(array_unique(array_map('intval', $varianteIds)));
        if ($varianteIds === []) {
            throw new TiendanubePrecioCsvException('Seleccione al menos una variante para exportar.', 'sin_filas');
        }

        $columnasResueltas = $this->resolverColumnas($preset, $columnas);
        $perfil = $this->perfiles->exigirValidado($storeId);
        $hashColumnas = $this->hashColumnas($columnasResueltas);
        $huella = hash('sha256', implode(',', $varianteIds).'|'.$hashColumnas);

        $existente = TiendanubePrecioCsvArtefacto::query()
            ->whereNull('lote_id')
            ->where('store_id', $storeId)
            ->where('revision_checksum', $huella)
            ->where('perfil_id', $perfil->id)
            ->where('columnas_hash', $hashColumnas)
            ->orderByDesc('id')
            ->first();
        if ($existente) {
            return $this->serializarArtefacto($existente);
        }

        $espejo = $this->filasProducto->cargarVariantes($varianteIds);
        $filas = [];
        foreach ($varianteIds as $varianteId) {
            $variante = $espejo[$varianteId] ?? null;
            if (! $variante) {
                throw new TiendanubePrecioCsvException(
                    'Una variante seleccionada ya no está en el catálogo.',
                    'identidad_faltante'
                );
            }
            $mapa = $this->filasProducto->mapaDesdeEspejo($variante);
            $this->serializador->validarIdentidad($mapa);
            $filas[] = $this->proyeccion($mapa, $columnasResueltas);
        }

        $artefacto = $this->persistir(
            $filas,
            $columnasResueltas,
            $preset,
            $perfil,
            $storeId,
            $userId,
            null,
            null,
            $huella,
            0,
            $hashColumnas
        );

        return $this->serializarArtefacto($artefacto);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorLote(string $loteId, int $storeId, int $userId): array
    {
        $this->lotes->obtenerAutorizado($loteId, $storeId, $userId);

        return TiendanubePrecioCsvArtefacto::query()
            ->where('lote_id', $loteId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (TiendanubePrecioCsvArtefacto $a) => $this->serializarArtefacto($a))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function descargar(int $artefactoId, int $storeId, int $userId): array
    {
        $artefacto = $this->obtenerArtefacto($artefactoId, $storeId, $userId);
        if ($artefacto->estado === TiendanubePrecioCsvArtefacto::ESTADO_GENERADO) {
            $artefacto->update([
                'estado' => TiendanubePrecioCsvArtefacto::ESTADO_DESCARGADO,
                'descargado_at' => now(),
            ]);
        }
        if ($artefacto->lote_id) {
            $lote = $this->lotes->obtenerAutorizado((string) $artefacto->lote_id, $storeId, $userId);
            $this->lotes->registrarEvento(
                $lote,
                $lote->revisionActual(),
                TiendanubePrecioLoteEvento::TIPO_CSV_DESCARGADO,
                $userId,
                ['artefacto_id' => $artefacto->id, 'hash' => $artefacto->hash_sha256]
            );
        }

        $abs = Storage::disk('local')->path($artefacto->archivo_path);
        if (! is_file($abs)) {
            throw new TiendanubePrecioCsvException('El archivo generado ya no está disponible.', 'archivo', 404);
        }

        return [
            'path' => $abs,
            'nombre' => $artefacto->nombre_archivo,
            'lote_estado' => $artefacto->lote?->estado,
            'artefacto' => $this->serializarArtefacto($artefacto->fresh() ?? $artefacto),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function declararImportacion(int $artefactoId, int $storeId, int $userId): array
    {
        $artefacto = $this->obtenerArtefacto($artefactoId, $storeId, $userId);
        $artefacto->update([
            'estado' => TiendanubePrecioCsvArtefacto::ESTADO_IMPORTACION_DECLARADA,
            'importacion_declarada_at' => now(),
        ]);
        if ($artefacto->lote_id) {
            $lote = $this->lotes->obtenerAutorizado((string) $artefacto->lote_id, $storeId, $userId);
            $this->lotes->registrarEvento(
                $lote,
                $lote->revisionActual(),
                TiendanubePrecioLoteEvento::TIPO_CSV_IMPORTACION_DECLARADA,
                $userId,
                ['artefacto_id' => $artefacto->id]
            );
        }

        return $this->serializarArtefacto($artefacto->fresh() ?? $artefacto);
    }

    /**
     * @param  list<string>|null  $columnas
     * @return list<string>
     */
    private function resolverColumnas(string $preset, ?array $columnas): array
    {
        try {
            return TiendanubePrecioCsvColumnaCatalogo::resolver($preset, $columnas);
        } catch (\InvalidArgumentException $e) {
            throw new TiendanubePrecioCsvException($e->getMessage(), 'columnas_invalidas', 422, [], $e);
        }
    }

    /**
     * @param  list<string>  $columnas
     */
    private function hashColumnas(array $columnas): string
    {
        return hash('sha256', implode('|', $columnas));
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    private function tieneCambioPrecio(array $campos): bool
    {
        foreach (['normal', 'promocional', 'costo_remoto'] as $clave) {
            $intencion = $campos[$clave]['intencion'] ?? TiendanubePrecioIntencion::Conservar->value;
            if ($intencion !== TiendanubePrecioIntencion::Conservar->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $mapa
     * @param  list<string>  $columnas
     * @return list<string>
     */
    private function proyeccion(array $mapa, array $columnas): array
    {
        $salida = [];
        foreach ($columnas as $columna) {
            $salida[] = $mapa[$columna] ?? '';
        }

        return $salida;
    }

    /**
     * @param  list<list<string>>  $filas
     * @param  list<string>  $columnas
     */
    private function persistir(
        array $filas,
        array $columnas,
        string $preset,
        TiendanubePrecioCsvPerfil $perfil,
        int $storeId,
        int $userId,
        ?string $loteId,
        ?int $revisionId,
        string $checksum,
        int $conCambio,
        string $hashColumnas
    ): TiendanubePrecioCsvArtefacto {
        $artefacto = TiendanubePrecioCsvArtefacto::query()->create([
            'lote_id' => $loteId,
            'revision_id' => $revisionId,
            'revision_checksum' => $checksum,
            'perfil_id' => $perfil->id,
            'perfil_version' => $perfil->version,
            'store_id' => $storeId,
            'preset_usado' => $preset,
            'columnas_exportadas' => $columnas,
            'columnas_hash' => $hashColumnas,
            'estado' => TiendanubePrecioCsvArtefacto::ESTADO_GENERADO,
            'archivo_path' => 'pendiente',
            'nombre_archivo' => 'pendiente.csv',
            'hash_sha256' => str_repeat('0', 64),
            'filas_exportadas' => 0,
            'filas_con_cambio_precio' => $conCambio,
            'filas_contexto' => 0,
            'generado_por' => $userId,
            'generado_at' => now(),
        ]);

        $relativo = 'tiendanube/precio-csv/'.$artefacto->id.'/export.csv';
        Storage::disk('local')->makeDirectory('tiendanube/precio-csv/'.$artefacto->id);
        $escrito = $this->escritor->escribir(
            Storage::disk('local')->path($relativo),
            $columnas,
            $filas
        );
        $nombre = $loteId
            ? 'tiendanube-precios-'.$storeId.'-rev'.$revisionId.'-'.$artefacto->id.'.csv'
            : 'tiendanube-productos-'.$storeId.'-'.$artefacto->id.'.csv';

        $artefacto->update([
            'archivo_path' => $relativo,
            'nombre_archivo' => $nombre,
            'hash_sha256' => $escrito['hash'],
            'filas_exportadas' => $escrito['filas'],
        ]);

        return $artefacto->fresh() ?? $artefacto;
    }

    private function obtenerArtefacto(int $artefactoId, int $storeId, int $userId): TiendanubePrecioCsvArtefacto
    {
        $artefacto = TiendanubePrecioCsvArtefacto::query()->find($artefactoId);
        if (! $artefacto || (int) $artefacto->store_id !== $storeId) {
            throw new TiendanubePrecioCsvException('Exportación no encontrada.', 'no_encontrada', 404);
        }
        if ($artefacto->lote_id) {
            $this->lotes->obtenerAutorizado((string) $artefacto->lote_id, $storeId, $userId);
        } elseif ((int) $artefacto->generado_por !== $userId) {
            throw new TiendanubePrecioCsvException('Exportación no encontrada.', 'no_encontrada', 404);
        }

        return $artefacto;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializarArtefacto(TiendanubePrecioCsvArtefacto $artefacto): array
    {
        return [
            'id' => $artefacto->id,
            'lote_id' => $artefacto->lote_id,
            'revision_id' => $artefacto->revision_id,
            'revision_checksum' => $artefacto->revision_checksum,
            'preset' => $artefacto->preset_usado,
            'columnas' => $artefacto->columnas_exportadas,
            'estado' => $artefacto->estado,
            'hash_sha256' => $artefacto->hash_sha256,
            'nombre_archivo' => $artefacto->nombre_archivo,
            'filas_exportadas' => (int) $artefacto->filas_exportadas,
            'filas_con_cambio_precio' => (int) $artefacto->filas_con_cambio_precio,
            'generado_at' => $artefacto->generado_at?->toIso8601String(),
            'descargado_at' => $artefacto->descargado_at?->toIso8601String(),
            'importacion_declarada_at' => $artefacto->importacion_declarada_at?->toIso8601String(),
            'importable' => true,
        ];
    }
}
