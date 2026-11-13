<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilaRevisionImportacionCliente extends Model
{
    public $timestamps = false;

    protected $table = 'filas_revision_importacion_clientes';

    protected $fillable = [
        'importacion_cliente_id',
        'numero_fila',
        'numero_cliente',
        'nombre',
        'categoria',
        'motivo',
        'monto_anterior',
        'monto_nuevo',
        'es_bloqueo',
        'es_aviso',
    ];

    protected $casts = [
        'es_bloqueo' => 'boolean',
        'es_aviso' => 'boolean',
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCliente::class, 'importacion_cliente_id');
    }
}
