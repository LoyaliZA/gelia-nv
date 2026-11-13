<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImporteProvisionalSolicitud extends Model
{
    public const TIPO_COTIZACION = 'cotizacion_aprobada';

    public const TIPO_PAGO = 'pago_confirmado';

    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_REVOCADO = 'revocado';

    public const ESTADO_CERRADO = 'cerrado_por_corte';

    public const INCLUSION_INDETERMINADA = 'indeterminada';

    protected $table = 'solicitud_importes_provisionales';

    protected $fillable = [
        'cliente_id',
        'solicitud_tag_id',
        'importe',
        'tipo',
        'estado',
        'importacion_cliente_id',
        'fecha_operacion',
        'fecha_corte',
        'origen',
        'usuario_id',
        'clave_operacion',
        'clave_vigente',
        'inclusion',
        'trazabilidad',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'fecha_operacion' => 'datetime',
        'fecha_corte' => 'datetime',
        'trazabilidad' => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_tag_id');
    }
}
