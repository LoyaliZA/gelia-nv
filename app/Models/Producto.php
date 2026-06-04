<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Producto extends Model
{
    protected $fillable = [
        'uuid',
        'folio',
        'almacen_id',
        'categoria_id',
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

    protected static function booted(): void
    {
        static::creating(function (Producto $producto) {
            if (empty($producto->uuid)) {
                $producto->uuid = (string) Str::uuid();
            }
            if (empty($producto->folio)) {
                // Generate a random folio if empty, or leave to DB if handled there.
                // Assuming unique folio needed.
                $producto->folio = 'PRD-' . strtoupper(Str::random(8));
            }
        });
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function categoria()
    {
        return $this->belongsTo(CatalogoCategoriaProducto::class, 'categoria_id');
    }

    public static function normalizarSku(string $sku): string
    {
        return ltrim(trim($sku), '0') ?: '0';
    }
}
