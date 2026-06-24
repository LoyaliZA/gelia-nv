<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LotePago extends Model
{
    public const ESTATUS_PENDIENTE = 'pendiente';

    public const ESTATUS_COMPLETADO = 'completado';

    protected $table = 'contabilidad_lotes_pago';

    protected $fillable = [
        'plataforma_pago_id',
        'fecha_corte_esperada',
        'fecha_deposito_real',
        'monto_ventas_total',
        'comisiones_plataforma_total',
        'monto_esperado_banco',
        'monto_real_banco',
        'estatus',
        'factura_referencia',
    ];

    protected $casts = [
        'fecha_corte_esperada' => 'date',
        'fecha_deposito_real' => 'date',
        'monto_ventas_total' => 'decimal:2',
        'comisiones_plataforma_total' => 'decimal:2',
        'monto_esperado_banco' => 'decimal:2',
        'monto_real_banco' => 'decimal:2',
    ];

    public function plataformaPago(): BelongsTo
    {
        return $this->belongsTo(PlataformaPago::class, 'plataforma_pago_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'lote_pago_id');
    }
}
