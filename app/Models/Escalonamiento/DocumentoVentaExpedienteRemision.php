<?php

namespace App\Models\Escalonamiento;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoVentaExpedienteRemision extends Model
{
    protected $table = 'documento_venta_expediente_remision';

    protected $fillable = [
        'documento_venta_id',
        'folio',
        'fecha',
        'cliente',
        'sucursal',
        'moneda',
        'subtotal',
        'descuento',
        'iva',
        'importe_ieps',
        'retencion_iva',
        'retencion_isr',
        'retencion_ieps',
        'total',
        'utilidad',
        'facturada',
        'email',
        'condicion_pago',
        'status',
        'vencimiento',
        'status_pago',
        'metodo_pago',
        'origen',
        'almacen',
        'vendedor',
        'plataforma',
        'numero_venta_plataforma',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'iva' => 'decimal:2',
        'importe_ieps' => 'decimal:2',
        'retencion_iva' => 'decimal:2',
        'retencion_isr' => 'decimal:2',
        'retencion_ieps' => 'decimal:2',
        'total' => 'decimal:2',
        'utilidad' => 'decimal:2',
        'facturada' => 'boolean',
    ];

    public function documentoVenta(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_id');
    }
}
