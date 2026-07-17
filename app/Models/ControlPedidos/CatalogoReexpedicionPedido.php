<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoReexpedicionPedido extends Model
{
    protected $table = 'catalogo_reexpedicion_pedido';

    protected $fillable = [
        'codigo_postal',
        'paqueteria_id',
        'costo_adicional',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'costo_adicional' => 'decimal:2',
    ];

    public function paqueteria(): BelongsTo
    {
        return $this->belongsTo(CatalogoPaqueteriaPedido::class, 'paqueteria_id');
    }

    public static function buscarActivo(string $codigoPostal, int|string|null $paqueteriaId): ?self
    {
        $cp = trim($codigoPostal);
        if ($cp === '' || $paqueteriaId === null || $paqueteriaId === '') {
            return null;
        }

        return static::query()
            ->where('activo', true)
            ->where('codigo_postal', $cp)
            ->where('paqueteria_id', $paqueteriaId)
            ->first();
    }
}
