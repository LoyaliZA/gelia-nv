<?php

namespace App\Models\Escalonamiento;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoMovimiento extends Model
{
    protected $table = 'escalonamiento_movimientos';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'cliente_id',
        'documento_venta_id',
        'efecto',
        'operacion',
        'escalonamiento_aplicacion_devolucion_id',
    ];

    protected $casts = [
        'efecto' => 'decimal:2',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoPeriodo::class, 'escalonamiento_periodo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_id');
    }

    public function aplicacionDevolucion(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoAplicacionDevolucion::class, 'escalonamiento_aplicacion_devolucion_id');
    }
}
