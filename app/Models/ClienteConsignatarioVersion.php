<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteConsignatarioVersion extends Model
{
    protected $table = 'cliente_consignatario_versiones';

    protected $fillable = [
        'consignatario_id', 'nombre', 'telefono', 'vigente', 'version', 'anterior_id', 'creado_por_id',
    ];

    protected $casts = ['vigente' => 'boolean', 'version' => 'integer'];

    public function consignatario(): BelongsTo
    {
        return $this->belongsTo(ClienteConsignatario::class, 'consignatario_id');
    }
}
