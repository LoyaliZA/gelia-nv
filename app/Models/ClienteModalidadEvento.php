<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteModalidadEvento extends Model
{
    protected $table = 'cliente_modalidad_eventos';

    protected $fillable = [
        'cliente_id',
        'modalidad',
        'origen',
        'solicitud_id',
        'usuario_id',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
