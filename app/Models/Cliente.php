<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero_cliente',
        'nombre',
        'lista_actual_id',
        'vendedor_id',
        'vendedor_original_id', // Agregado a la asignación masiva
        'monto_venta_actual',
        'es_heredado'
    ];

    protected $casts = [
        'monto_venta_actual' => 'decimal:2',
        'es_heredado' => 'boolean',
    ];

    /**
     * Relación: La vendedora que atiende actualmente al cliente.
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * Relación: La vendedora que prospectó originalmente al cliente (Para cálculo de comisiones residuales).
     */
    public function vendedorOriginal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_original_id');
    }

    public function listaDescuento(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_actual_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTag::class);
    }
}