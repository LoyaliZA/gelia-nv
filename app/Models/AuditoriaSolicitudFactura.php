<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaSolicitudFactura extends Model
{
    protected $table = 'auditorias_solicitudes_facturas';

    protected $fillable = [
        'solicitud_factura_id',
        'usuario_id',
        'estado_anterior_id',
        'estado_nuevo_id',
        'motivo_reporte',
        'datos_snapshot',
    ];

    protected $casts = [
        'datos_snapshot' => 'array',
    ];

    public function solicitudFactura(): BelongsTo
    {
        return $this->belongsTo(SolicitudFactura::class, 'solicitud_factura_id');
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
