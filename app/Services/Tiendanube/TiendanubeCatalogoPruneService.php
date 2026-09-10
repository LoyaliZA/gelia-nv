<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeCategoria;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeSyncLog;
use App\Models\Tiendanube\TiendanubeSyncRecursoVisto;

class TiendanubeCatalogoPruneService
{
    public function __construct(
        private TiendanubeApiClient $api,
        private TiendanubeSyncRecursoVistoService $vistos
    ) {}

    /**
     * @return array{eliminados_productos: int, eliminados_categorias: int, pendientes: int, omitida: bool}
     */
    public function confirmarYDepurar(TiendanubeSyncLog $log, TiendanubeCatalogoSyncService $sync): array
    {
        $candidatosProductos = $this->vistos->candidatos($log, TiendanubeSyncRecursoVisto::TIPO_PRODUCTO);
        $candidatosCategorias = $this->vistos->candidatos($log, TiendanubeSyncRecursoVisto::TIPO_CATEGORIA);

        $log->update([
            'candidatos_productos' => count($candidatosProductos),
            'candidatos_categorias' => count($candidatosCategorias),
        ]);

        if (! (bool) config('tiendanube.sync_prune_enabled', false)) {
            return [
                'eliminados_productos' => 0,
                'eliminados_categorias' => 0,
                'pendientes' => 0,
                'omitida' => true,
            ];
        }

        $totalCandidatos = count($candidatosProductos) + count($candidatosCategorias);
        $umbral = max(0, (int) config('tiendanube.sync_prune_confirm_threshold', 10));

        if ($totalCandidatos > $umbral && ! $log->confirmar_depuracion_masiva) {
            return [
                'eliminados_productos' => 0,
                'eliminados_categorias' => 0,
                'pendientes' => 0,
                'omitida' => true,
            ];
        }

        $pendientes = 0;
        $eliminadosProductos = 0;
        $eliminadosCategorias = 0;

        foreach ($candidatosProductos as $id) {
            $consulta = $this->api->consultarProducto($id);
            if ($consulta['estado'] === 'existe' && is_array($consulta['recurso'])) {
                $sync->upsertProducto($consulta['recurso']);

                continue;
            }
            if ($consulta['estado'] === 'ausente') {
                TiendanubeProducto::query()->whereKey($id)->delete();
                $eliminadosProductos++;

                continue;
            }
            $pendientes++;
        }

        foreach ($candidatosCategorias as $id) {
            $consulta = $this->api->consultarCategoria($id);
            if ($consulta['estado'] === 'existe' && is_array($consulta['recurso'])) {
                $sync->upsertCategoria($consulta['recurso']);

                continue;
            }
            if ($consulta['estado'] === 'ausente') {
                TiendanubeCategoria::query()->whereKey($id)->delete();
                $eliminadosCategorias++;

                continue;
            }
            $pendientes++;
        }

        return [
            'eliminados_productos' => $eliminadosProductos,
            'eliminados_categorias' => $eliminadosCategorias,
            'pendientes' => $pendientes,
            'omitida' => false,
        ];
    }
}
