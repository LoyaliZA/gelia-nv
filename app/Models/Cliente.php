<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'monto_credito_autorizado',
        'dias_credito',
        'fecha_inicio_credito',
        'alerta_aumento_credito',
    ];

    protected $casts = [
        'monto_venta_actual' => 'decimal:2',
        'es_heredado' => 'boolean',
        'es_inactivo' => 'boolean',
        'lista_bloqueada' => 'boolean',
        'monto_credito_autorizado' => 'decimal:2',
        'dias_credito' => 'integer',
        'fecha_inicio_credito' => 'date:Y-m-d',
        'alerta_aumento_credito' => 'boolean',
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

    public function bitacorasCobranza(): HasMany
    {
        return $this->hasMany(CobranzaBitacora::class)->orderBy('created_at', 'desc');
    }

    public function facturasCobranza(): HasMany
    {
        return $this->hasMany(CobranzaFactura::class, 'cliente_id');
    }

    public function facturasActivas(): HasMany
    {
        return $this->hasMany(CobranzaFactura::class, 'cliente_id')->where('pagada', false);
    }

    public function facturaCobranzaActiva(): HasOne
    {
        return $this->hasOne(CobranzaFactura::class, 'cliente_id')
            ->where('pagada', false)
            ->latestOfMany();
    }

    public function alertasCobranza(): HasMany
    {
        return $this->hasMany(CobranzaAlerta::class, 'cliente_id');
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