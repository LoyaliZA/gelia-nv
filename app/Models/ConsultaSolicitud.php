<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultaSolicitud extends Model
{
    use BelongsToUsuario;
    protected $table = 'consultas_solicitud';

    protected $fillable = [
        'solicitud_id',
        'vendedor_id',
        'consulta_tag',
        'consulta_lista',
        'comentario_vendedor',
        'estado',
        'respuesta_positiva',
        'comentario_encargada',
        'evidencia_respuesta_path',
        'encargada_id',
        'leido_vendedor_at',
    ];

    protected $casts = [
        'consulta_tag' => 'boolean',
        'consulta_lista' => 'boolean',
        'respuesta_positiva' => 'boolean',
        'leido_vendedor_at' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsToUsuario('vendedor_id');
    }

    public function encargada(): BelongsTo
    {
        return $this->belongsToUsuario('encargada_id');
    }
}
