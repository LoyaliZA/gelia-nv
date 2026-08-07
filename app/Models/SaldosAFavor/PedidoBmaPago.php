<?php

namespace App\Models\SaldosAFavor;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PedidoBmaPago extends Model
{
    public const REVISION_PENDIENTE = 'pendiente';
    public const REVISION_CONFIRMADO = 'confirmado';
    public const REVISION_CON_DIFERENCIA = 'con_diferencia';

    public const FORMAS_PAGO = [
        'transferencia',
        'efectivo',
        'tarjeta',
        'otro',
    ];

    protected $table = 'pedido_bma_pagos';

    protected $fillable = [
        'pedido_bma_id',
        'numero_exhibicion',
        'monto',
        'catalogo_banco_id',
        'forma_pago',
        'fecha_pago',
        'referencia',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'capturado_por_id',
        'estado_revision',
        'revisado_por_id',
        'revisado_at',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'numero_exhibicion' => 'integer',
        'tamano_bytes' => 'integer',
        'fecha_pago' => 'datetime',
        'revisado_at' => 'datetime',
    ];

    protected $appends = ['url'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function capturadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturado_por_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->ruta_archivo
            ? Storage::disk('public')->url($this->ruta_archivo)
            : null;
    }
}
