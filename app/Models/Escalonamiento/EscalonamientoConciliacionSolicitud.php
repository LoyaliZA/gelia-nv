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

    public const ESTADO_ASIGNADA = 'asignada';

    public const ESTADO_INDETERMINADA = 'indeterminada';

    public const ESTADO_SIN_DISPONIBILIDAD = 'sin_disponibilidad';

    public const ESTADO_ANULADA = 'anulada';

    public const ESTADO_REEMPLAZADA = 'reemplazada';

    public const PROCEDENCIA_DOCUMENTO = 'documento';

    public const PROCEDENCIA_HISTORICA = 'historica_sin_respaldo';

    public const PROCEDENCIA_IDENTIDAD = 'identidad_insuficiente';

    public const PROCEDENCIA_AMBIGUA = 'identidad_ambigua';

    public const PROCEDENCIA_PAGO_PENDIENTE = 'pago_no_confirmado';

    public const INCLUSION_INCLUIDA = 'incluida';

    public const INCLUSION_FUERA = 'fuera';

    public const INCLUSION_INDETERMINADA = 'indeterminada';

    public const CLAVE_SIN_DOCUMENTO = 'sin-documento';

    protected $table = 'escalonamiento_conciliaciones_solicitud';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'solicitud_tag_id',
        'documento_venta_id',
        'importe_asignado',
        'estado',
        'procedencia',
        'inclusion_acumulado',
        'clave_asignacion',
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
