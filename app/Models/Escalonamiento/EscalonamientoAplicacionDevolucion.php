<?php

namespace App\Models\Escalonamiento;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoAplicacionDevolucion extends Model
{
    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_REVERTIDA = 'revertida';

    public const ESTADO_CANCELADA = 'cancelada';

    protected $table = 'escalonamiento_aplicaciones_devolucion';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'documento_devolucion_id',
        'documento_venta_original_id',
        'documento_remision_vinculada_id',
        'importe',
        'estado',
        'evidencia',
        'user_id',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoPeriodo::class, 'escalonamiento_periodo_id');
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_devolucion_id');
    }

    public function ventaOriginal(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_original_id');
    }

    public function remisionVinculada(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_remision_vinculada_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }
}
