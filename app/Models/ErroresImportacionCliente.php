<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErroresImportacionCliente extends Model
{
    protected $table = 'errores_importacion_clientes';

    protected $fillable = [
        'importacion_cliente_id',
        'numero_fila',
        'numero_cliente',
        'mensaje',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCliente::class, 'importacion_cliente_id');
    }
}
