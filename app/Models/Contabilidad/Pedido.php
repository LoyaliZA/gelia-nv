<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    protected $table = 'contabilidad_pedidos';

    protected $fillable = [
        'fecha_salida',
        'numero_pedido',
        'cliente_nombre',
        'tipo_transaccion_id',
        'plataforma_pago_id',
        'lote_pago_id',
        'venta_total',
        'costo_envio',
        'envio_pagado_cliente',
        'comision_base',
        'comision_iva',
        'tasa_comision_pct',
        'cuota_fija',
        'comision_plataforma',
        'utilidad_total',
        'estatus_pago_id',
        'comision_transferencia',
        'fecha_retiro',
        'bloqueado',
    ];

    protected $casts = [
        'fecha_salida' => 'date',
        'fecha_retiro' => 'date',
        'venta_total' => 'decimal:2',
        'costo_envio' => 'decimal:2',
        'envio_pagado_cliente' => 'boolean',
        'comision_base' => 'decimal:2',
        'comision_iva' => 'decimal:2',
        'tasa_comision_pct' => 'decimal:2',
        'cuota_fija' => 'decimal:2',
        'comision_plataforma' => 'decimal:2',
        'utilidad_total' => 'decimal:2',
        'comision_transferencia' => 'decimal:2',
        'bloqueado' => 'boolean',
    ];

    public function tipoTransaccion(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoTransaccion::class, 'tipo_transaccion_id');
    }

    public function plataformaPago(): BelongsTo
    {
        return $this->belongsTo(PlataformaPago::class, 'plataforma_pago_id');
    }

    public function lotePago(): BelongsTo
    {
        return $this->belongsTo(LotePago::class, 'lote_pago_id');
    }

    public function estatusPago(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstatusPago::class, 'estatus_pago_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class, 'pedido_id');
    }

    public function estaTransferido(): bool
    {
        return (int) $this->estatus_pago_id === CatalogoEstatusPago::TRANSFERIDO;
    }

    public function calcularMontoEsperadoBanco(): float
    {
        $codigo = strtolower($this->tipoTransaccion?->codigo ?? 'venta');
        if (str_contains($codigo, 'venta')) {
            return round((float) $this->venta_total - (float) $this->comision_plataforma, 2);
        }
        return round(-abs((float) $this->venta_total + (float) $this->comision_plataforma), 2);
    }

    public function calcularCostoProductos(): float
    {
        $txCode = strtolower($this->tipoTransaccion?->codigo ?? 'venta');
        $costo = 0.0;
        foreach ($this->lineas as $linea) {
            $tipoDevolucion = $linea->tipo_devolucion ?? 'normal';
            $esCosto = ($txCode === 'reembolso')
                ? ($tipoDevolucion === 'perdido_danado')
                : ($tipoDevolucion === 'normal' || $tipoDevolucion === 'perdido_danado');

            if ($esCosto) {
                $costo += (float) $linea->subtotal;
            }
        }
        return $costo;
    }

    public function calcularUtilidad(float $costoProductos): float
    {
        $txCode = strtolower($this->tipoTransaccion?->codigo ?? 'venta');
        $gastoEnvioEmpresa = $this->envio_pagado_cliente ? 0.0 : (float) $this->costo_envio;

        if (str_contains($txCode, 'venta')) {
            $utilidad = (float) $this->venta_total - $costoProductos - $gastoEnvioEmpresa - (float) $this->comision_plataforma;
        } else {
            $utilidad = -($costoProductos + $gastoEnvioEmpresa + (float) $this->comision_plataforma);
        }

        if ($this->estaTransferido()) {
            $utilidad -= (float) $this->comision_transferencia;
        }

        return round($utilidad, 2);
    }
}
