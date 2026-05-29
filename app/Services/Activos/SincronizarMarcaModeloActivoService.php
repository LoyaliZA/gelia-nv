<?php

namespace App\Services\Activos;

use App\Models\CatalogoMarcaActivo;
use App\Models\CatalogoModeloActivo;
use App\Models\CatalogoTipoActivo;
use Illuminate\Support\Str;

class SincronizarMarcaModeloActivoService
{
    public function ejecutar(CatalogoTipoActivo $tipo, array &$atributos): void
    {
        if (!empty($atributos['marca'])) {
            $marca = $this->syncMarca($tipo, $atributos['marca']);
            $atributos['marca_id'] = $marca->id;
            $atributos['marca'] = $marca->nombre;

            if (!empty($atributos['modelo'])) {
                $modelo = $this->syncModelo($marca, $atributos['modelo']);
                $atributos['modelo_id'] = $modelo->id;
                $atributos['modelo'] = $modelo->nombre;
            }
        }
    }

    private function syncMarca(CatalogoTipoActivo $tipo, string $nombre): CatalogoMarcaActivo
    {
        $slug = Str::slug($nombre);

        return CatalogoMarcaActivo::updateOrCreate(
            ['catalogo_tipo_activo_id' => $tipo->id, 'slug' => $slug],
            ['nombre' => trim($nombre), 'activo' => true]
        );
    }

    private function syncModelo(CatalogoMarcaActivo $marca, string $nombre): CatalogoModeloActivo
    {
        $slug = Str::slug($nombre);

        return CatalogoModeloActivo::updateOrCreate(
            ['catalogo_marca_activo_id' => $marca->id, 'slug' => $slug],
            ['nombre' => trim($nombre), 'activo' => true]
        );
    }
}
