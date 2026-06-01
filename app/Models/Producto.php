<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'uuid',
        'folio',
        'sku',
        'descripcion',
        'existencia',
        'costo',
        'precio_venta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'existencia' => 'integer',
            'costo' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public static function normalizarSku(string $sku): string
    {
        return ltrim(trim($sku), '0') ?: '0';
    }
}
