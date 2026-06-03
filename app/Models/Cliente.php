<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'rfc',
        'codigo_postal',
        'regimen_fiscal',
        'correo_electronico',
        'uso_factura',
        'nombre_razon_social',
        'lista_actual_id',
        'vendedor_id',
        'vendedor_original_id', // Agregado a la asignación masiva
        'monto_venta_actual',
        'es_heredado',
        'es_inactivo',
        'catalogo_tipo_cliente_id',
        'lista_bloqueada', // <-- NUEVO CAMPO PARA CONTROLAR BLOQUEO DE LISTA
    ];

    protected $casts = [
        'monto_venta_actual' => 'decimal:2',
        'es_heredado' => 'boolean',
        'es_inactivo' => 'boolean',
        'lista_bloqueada' => 'boolean',
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

    public function tipo(): BelongsTo
    {
        // <-- CORREGIDO: Se quitó la "s" en la llave foránea
        return $this->belongsTo(CatalogoTipoCliente::class, 'catalogo_tipo_cliente_id');
    }

    public function historialMontos(): HasMany
    {
        return $this->hasMany(HistorialMontoCliente::class);
    }

    /**
     * Orden numérico del número de cliente (menor ↔ mayor), con desempate alfabético.
     */
    public function scopeOrdenarPorNumeroCliente(Builder $query, string $direction = 'asc'): Builder
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $driver = $query->getConnection()->getDriverName();

        $cast = match ($driver) {
            'mysql', 'mariadb' => 'CAST(numero_cliente AS UNSIGNED)',
            default => 'CAST(numero_cliente AS INTEGER)',
        };

        return $query
            ->orderByRaw("{$cast} {$dir}")
            ->orderBy('numero_cliente', $dir);
    }
}