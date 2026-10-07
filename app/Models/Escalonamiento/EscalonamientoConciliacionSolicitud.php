<?php

namespace App\Models\Escalonamiento;

use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoConciliacionSolicitud extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_CUBIERTA = 'cubierta';

    public const ESTADO_RECHAZADA = 'rechazada';

    protected $table = 'escalonamiento_conciliaciones_solicitud';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'solicitud_tag_id',
        'documento_venta_id',
        'importe_asignado',
        'estado',
        'evidencia',
        'user_id',
    ];

    protected $casts = [
        'importe_asignado' => 'decimal:2',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoPeriodo::class, 'escalonamiento_periodo_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_tag_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoVenta::class, 'documento_venta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
