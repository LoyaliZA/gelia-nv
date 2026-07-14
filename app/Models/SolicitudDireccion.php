<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudDireccion extends Model
{
    public const ACCION_PRIMERA = 'register_first_address';

    public const ACCION_ADICIONAL = 'add_address';

    public const ACCION_ACTUALIZAR = 'update_address';

    public const ESTADO_PENDING = 'pending';

    public const ESTADO_VERIFIED = 'verified';

    public const ESTADO_REJECTED = 'rejected';

    public const ESTADO_REQUIRES_CORRECTION = 'requires_correction';

    public const ESTADO_POSSIBLE_DUPLICATE = 'possible_duplicate';

    public const ESTADO_IDENTITY_REVIEW = 'identity_review_required';

    public const ESTADO_APPROVED = 'approved';

    public const REMISION_PENDING_ORDER_LINK = 'pending_order_link';

    public const REMISION_LINKED = 'linked';

    public const REMISION_REJECTED = 'rejected';

    protected $table = 'solicitudes_direccion';

    protected $fillable = [
        'folio',
        'token_publico_id',
        'numero_cliente_declarado',
        'cliente_coincidente_id',
        'accion_solicitada',
        'direccion_seleccionada_id',
        'nombre_declarado',
        'telefono_declarado',
        'correo_declarado',
        'datos_solicitados_json',
        'anexa_remision',
        'archivo_remision',
        'estado',
        'notas_validacion',
        'estado_remision',
        'revisada_por',
        'revisada_en',
    ];

    protected $casts = [
        'datos_solicitados_json' => 'array',
        'anexa_remision' => 'boolean',
        'revisada_en' => 'datetime',
    ];

    public function enlace(): BelongsTo
    {
        return $this->belongsTo(EnlaceDireccion::class, 'token_publico_id');
    }

    public function clienteCoincidente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_coincidente_id');
    }

    public function direccionSeleccionada(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccion::class, 'direccion_seleccionada_id');
    }

    public function revisadaPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }
}
