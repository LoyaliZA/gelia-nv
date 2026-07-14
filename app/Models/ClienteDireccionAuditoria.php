<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteDireccionAuditoria extends Model
{
    protected $table = 'cliente_direccion_auditorias';

    protected $fillable = [
        'cliente_id',
        'cliente_direccion_id',
        'solicitud_direccion_id',
        'usuario_id',
        'accion',
        'origen',
        'datos_anteriores',
        'datos_nuevos',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccion::class, 'cliente_direccion_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudDireccion::class, 'solicitud_direccion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
