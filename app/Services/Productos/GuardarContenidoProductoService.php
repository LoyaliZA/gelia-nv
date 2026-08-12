<?php

namespace App\Services\Productos;

use App\Models\CanalComercial;
use App\Models\Producto;
use App\Models\ProductoContenido;

class GuardarContenidoProductoService
{
    /**
     * @param  array{pitch_venta?:string,descripcion_larga?:string,descripcion_corta?:string,seo_titulo?:string,seo_descripcion?:string,titulo_comercial?:string,canal?:string,idioma?:string}  $data
     */
    public function upsertInterno(Producto $producto, array $data): ProductoContenido
    {
        $canalCodigo = $data['canal'] ?? 'interno';
        $canal = CanalComercial::query()->where('codigo', $canalCodigo)->first();
        $idioma = $data['idioma'] ?? 'es';

        return ProductoContenido::query()->updateOrCreate(
            [
                'producto_id' => $producto->id,
                'canal_id' => $canal?->id,
                'idioma' => $idioma,
            ],
            [
                'titulo_comercial' => $data['titulo_comercial'] ?? null,
                'descripcion_corta' => $data['descripcion_corta'] ?? $producto->descripcion_corta,
                'descripcion_larga' => $data['descripcion_larga'] ?? null,
                'pitch_venta' => $data['pitch_venta'] ?? null,
                'seo_titulo' => $data['seo_titulo'] ?? null,
                'seo_descripcion' => $data['seo_descripcion'] ?? null,
                'estado' => true,
            ]
        );
    }
}
