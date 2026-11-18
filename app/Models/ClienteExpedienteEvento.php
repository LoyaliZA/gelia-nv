<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteExpedienteEvento extends Model
{
    protected $table = 'cliente_expediente_eventos';

    protected $fillable = [
        'cliente_id', 'consignatario_id', 'documento_id', 'accion', 'usuario_id', 'detalle',
    ];

    protected $casts = ['detalle' => 'array'];
}
