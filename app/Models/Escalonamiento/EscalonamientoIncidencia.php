<?php

namespace App\Models\Escalonamiento;

use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoIncidencia extends Model
{
    protected $table = 'escalonamiento_incidencias';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'cliente_id',
        'documento_venta_id',
        'solicitud_tag_id',
        'gravedad',
        'codigo',
        'motivo',
        'estado',
        'resolucion',
        'resuelto_en',
        'resuelto_por_user_id',
        'user_id',
    ];

    protected $casts = [
        'resuelto_en' => 'datetime',
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

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_tag_id');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_user_id');
    }
}
