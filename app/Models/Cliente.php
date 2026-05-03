<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use SoftDeletes; // Permite borrado lógico

    // Variables seguras para asignación masiva
    protected $fillable = [
        'numero_cliente',
        'nombre',
        'lista_actual_id',
        'vendedor_id',
        'monto_venta_actual',
        'es_heredado'
    ];

    // Casteo de variables para asegurar el tipo de dato
    protected $casts = [
        'monto_venta_actual' => 'decimal:2',
        'es_heredado' => 'boolean',
    ];

    /**
     * Relación: Un cliente pertenece a un vendedor (Usuario)
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * Relación: Un cliente pertenece a una lista de descuento
     */
    public function listaDescuento(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_actual_id');
    }

    /**
     * Relación: Un cliente puede tener muchas solicitudes
     */
    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTag::class);
    }
}