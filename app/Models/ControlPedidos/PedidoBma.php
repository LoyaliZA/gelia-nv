<?php

namespace App\Models\ControlPedidos;

use App\Models\CatalogoBanco;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoBma extends Model
{
    use SoftDeletes;

    protected $table = 'pedidos_bma';

    protected $fillable = [
        'folio',
        'fecha',
        'vendedor_id',
        'cliente_id',
        'catalogo_almacen_salida_id',
        'catalogo_banco_id',
        'requiere_factura',
        'saldo_a_favor',
        'catalogo_tipo_caja_id',
        'numero_cajas',
        'peso_real_kg',
        'peso_volumetrico_kg',
        'peso_con_productos_kg',
        'catalogo_paqueteria_id',
        'catalogo_tipo_guia_id',
        'catalogo_zona_id',
        'catalogo_envio_tienda_id',
        'envio_tienda_otro',
        'codigo_postal',
        'domicilio_entrega',
        'envia_otra_persona',
        'es_resguardo',
        'envia_a_otra_persona',
        'total_mercancia',
        'costo_envio',
        'aplica_seguro',
        'costo_seguro',
        'total_a_cobrar',
        'catalogo_estatus_pedido_id',
        'comentarios_drive',
        'numero_rastreo',
        'motivo_rechazo',
        'pago_validado_at',
        'pago_validado_por_id',
        'empacado_at',
        'empacado_por_id',
        'detalle_incidencia_empaque',
        'incidencia_empaque_at',
        'incidencia_empaque_por_id',
    ];

    protected $casts = [
        'pago_validado_at' => 'datetime',
        'empacado_at' => 'datetime',
        'incidencia_empaque_at' => 'datetime',
        'fecha' => 'date',
        'requiere_factura' => 'boolean',
        'aplica_seguro' => 'boolean',
        'es_resguardo' => 'boolean',
        'envia_a_otra_persona' => 'boolean',
        'saldo_a_favor' => 'decimal:2',
        'peso_real_kg' => 'decimal:4',
        'peso_volumetrico_kg' => 'decimal:4',
        'peso_con_productos_kg' => 'decimal:4',
        'total_mercancia' => 'decimal:2',
        'costo_envio' => 'decimal:2',
        'costo_seguro' => 'decimal:2',
        'total_a_cobrar' => 'decimal:2',
        'numero_cajas' => 'integer',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function envioTienda(): BelongsTo
    {
        return $this->belongsTo(CatalogoEnvioTienda::class, 'catalogo_envio_tienda_id');
    }

    public function estatus(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstatusPedido::class, 'catalogo_estatus_pedido_id');
    }

    public function almacenSalida(): BelongsTo
    {
        return $this->belongsTo(CatalogoAlmacenSalida::class, 'catalogo_almacen_salida_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function tipoCaja(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoCajaPedido::class, 'catalogo_tipo_caja_id');
    }

    public function paqueteria(): BelongsTo
    {
        return $this->belongsTo(CatalogoPaqueteriaPedido::class, 'catalogo_paqueteria_id');
    }

    public function tipoGuia(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoGuiaPedido::class, 'catalogo_tipo_guia_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(CatalogoZonaPedido::class, 'catalogo_zona_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')->orderBy('orden');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(PedidoBmaHistorialEstado::class, 'pedido_bma_id')->orderByDesc('created_at');
    }

    public function pagoValidadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_validado_por_id');
    }

    public function empacadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empacado_por_id');
    }

    public function incidenciaEmpaquePor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incidencia_empaque_por_id');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')
            ->where('tipo', PedidoBmaDocumento::TIPO_COMPROBANTE)
            ->orderBy('orden');
    }

    public function remision(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')
            ->where('tipo', PedidoBmaDocumento::TIPO_REMISION)
            ->orderBy('orden');
    }

    public function tienePagoValidado(): bool
    {
        return $this->pago_validado_at !== null;
    }

    public function tieneRemision(): bool
    {
        return $this->remision()->exists();
    }

    public function esAuditablePorAuxiliar(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR;
    }

    public function esEditablePorVendedora(): bool
    {
        $fase = $this->estatus?->fase_ciclo;

        return in_array($fase, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        ], true);
    }

    public function esBorrador(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_BORRADOR;
    }

    public function esGestionablePorCedis(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            CatalogoEstatusPedido::FASE_EN_RUTA,
        ], true);
    }

    public function puedeMarcarEmpacado(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ], true);
    }

    public function puedeRevertirEmpacado(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_EN_RUTA;
    }

    public function puedeReportarIncidencia(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_EN_CEDIS;
    }

    public static function calcularTotal(float $mercancia, float $envio, bool $seguro, float $costoSeguro, float $saldoFavor): float
    {
        $total = $mercancia + $envio + ($seguro ? $costoSeguro : 0) - $saldoFavor;

        return max(0, round($total, 2));
    }
}
