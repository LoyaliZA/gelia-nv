<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class ContabilidadConfiguracion extends Model
{
    protected $table = 'contabilidad_configuracion';

    protected $fillable = [
        'mapeo_precios',
    ];

    protected $casts = [
        'mapeo_precios' => 'array',
    ];

    public const MAPEO_PRECIOS_DEFAULT = [
        'sku' => 'SKU',
        'precio_base' => 'Bronce',
        'descripcion' => 'Descripcion',
    ];

    public static function obtener(): self
    {
        return static::firstOrCreate([], [
            'mapeo_precios' => self::MAPEO_PRECIOS_DEFAULT,
        ]);
    }

    /**
     * @return array{sku: string, precio_base: string, descripcion: string}
     */
    public function mapeoPreciosEfectivo(): array
    {
        $mapeo = $this->mapeo_precios;

        if (! is_array($mapeo) || empty($mapeo['sku']) || empty($mapeo['precio_base'])) {
            return self::MAPEO_PRECIOS_DEFAULT;
        }

        return [
            'sku' => (string) $mapeo['sku'],
            'precio_base' => (string) $mapeo['precio_base'],
            'descripcion' => (string) ($mapeo['descripcion'] ?? self::MAPEO_PRECIOS_DEFAULT['descripcion']),
        ];
    }
}
