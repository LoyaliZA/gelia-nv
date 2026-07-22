<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaSolicitudTraspaso extends Model
{
    protected $table = 'auditorias_solicitudes_traspasos';

    protected $fillable = [
        'solicitud_traspaso_id',
        'usuario_id',
        'estado_anterior_id',
        'estado_nuevo_id',
        'motivo_reporte',
        'datos_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'datos_snapshot' => 'array',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTraspaso::class, 'solicitud_traspaso_id');
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
