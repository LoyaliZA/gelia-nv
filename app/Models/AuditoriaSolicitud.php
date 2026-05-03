<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaSolicitud extends Model
{
    protected $table = 'auditorias_solicitudes';

    protected $fillable = [
        'solicitud_id',
        'usuario_id',
        'estado_anterior_id',
        'estado_nuevo_id',
        'motivo_reporte'
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstadoSolicitud::class, 'estado_anterior_id');
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstadoSolicitud::class, 'estado_nuevo_id');
    }
}