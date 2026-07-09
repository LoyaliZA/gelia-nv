<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\ContabilidadConfiguracion;

class ActualizarConfiguracionContabilidadService
{
    /**
     * @param  array{sku: string, precio_base: string, descripcion?: string}  $mapeoPrecios
     */
    public function ejecutar(array $mapeoPrecios): ContabilidadConfiguracion
    {
        $config = ContabilidadConfiguracion::obtener();
        $config->update([
            'mapeo_precios' => [
                'sku' => (string) $mapeoPrecios['sku'],
                'precio_base' => (string) $mapeoPrecios['precio_base'],
                'descripcion' => (string) ($mapeoPrecios['descripcion'] ?? ContabilidadConfiguracion::MAPEO_PRECIOS_DEFAULT['descripcion']),
            ],
        ]);

        return $config->fresh();
    }
}
