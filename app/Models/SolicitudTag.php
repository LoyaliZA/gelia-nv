<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudTag extends Model
{
    use SoftDeletes;

    // Explicitamos la tabla ya que Laravel en plural podría buscar "solicitud_tags"
    protected $table = 'solicitudes_tags';

    protected $fillable = [
        'cliente_id',
        'vendedor_id',
        'departamento_id', // <-- NUEVO CAMPO AÑADIDO
        'catalogo_proceso_id',
        'catalogo_estado_solicitud_id',
        'monto_cotizado',
        'pago_confirmado',
        'observaciones_vendedor',
        'evidencia_path',
        'catalogo_tipo_cliente_id',
        'catalogo_lista_descuento_id', // <-- NUEVO CAMPO AÑADIDO
        'confirmo_informacion_escalonamiento',
        'monto_final_tentativo',
        'total_proyectado_neto',
        'motivo_incorrecta',
        'rollback_confirmado_at',
        'numero_remision',
        'numero_pedido',
        'fecha_operacion',
        'motivo_operacion',
        'catalogo_banco_id',
        'solicitar_cotizacion',
        'compra_en_tienda',
        'cancelacion_solicitada_at',
        'motivo_cancelacion',
        'catalogo_lista_rebaja_id',
    ];

    protected $casts = [
        'monto_cotizado' => 'decimal:2',
        'monto_final_tentativo' => 'decimal:2',
        'total_proyectado_neto' => 'decimal:2',
        'pago_confirmado' => 'boolean',
        'confirmo_informacion_escalonamiento' => 'boolean',
        'rollback_confirmado_at' => 'datetime',
        'fecha_operacion' => 'date',
        'solicitar_cotizacion' => 'boolean',
        'compra_en_tienda' => 'boolean',
        'cancelacion_solicitada_at' => 'datetime',
    ];

    // Relaciones (BelongsTo) hacia los catálogos y entidades
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function vendedor(): BelongsTo { return $this->belongsTo(User::class, 'vendedor_id'); }
    public function departamento(): BelongsTo { return $this->belongsTo(Departamento::class); }
    public function proceso(): BelongsTo { return $this->belongsTo(CatalogoProceso::class, 'catalogo_proceso_id'); }
    public function estado(): BelongsTo { return $this->belongsTo(CatalogoEstadoSolicitud::class, 'catalogo_estado_solicitud_id'); }
    public function tipoCliente(): BelongsTo { return $this->belongsTo(CatalogoTipoCliente::class, 'catalogo_tipo_cliente_id'); }
    public function listaDescuento(): BelongsTo { return $this->belongsTo(CatalogoListaDescuento::class, 'catalogo_lista_descuento_id'); }
    public function listaRebaja(): BelongsTo { return $this->belongsTo(CatalogoListaDescuento::class, 'catalogo_lista_rebaja_id'); }
    /**
     * Relación: Una solicitud tiene un historial de auditorías
     */
    public function auditorias(): HasMany
    {
        return $this->hasMany(AuditoriaSolicitud::class, 'solicitud_id');
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(ConsultaSolicitud::class, 'solicitud_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function esProcesoOperativo(): bool
    {
        return $this->proceso?->esOperativo() ?? false;
    }

    /**
     * Solicitudes sujetas al plazo de 48 h por falta de pago.
     * Excluye procesos operativos (cancelación remisión/pedido, cotización sobre pedido, etc.):
     * no requieren monto de cotización ni confirmación de pago.
     */
    public function scopeSujetasAPlazoDePago(Builder $query): Builder
    {
        return $query
            ->where('pago_confirmado', false)
            ->whereIn('catalogo_estado_solicitud_id', [1, 2])
            ->whereHas('proceso', function (Builder $proceso) {
                $proceso->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO);
            });
    }
}