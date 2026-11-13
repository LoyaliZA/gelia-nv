<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictoImportacionCliente extends Model
{
    public const PENDIENTE = 'pendiente';

    public const RESUELTO = 'resuelto';

    protected $table = 'conflictos_importacion_cliente';

    protected $fillable = [
        'importacion_cliente_id', 'cliente_id', 'numero_cliente', 'numero_fila', 'alcance', 'estado',
        'motivo', 'valor_vigente', 'valor_propuesto', 'origen_vigente', 'origen_propuesto',
        'solicitud_tag_id', 'resuelto_por', 'decision', 'resuelto_at',
    ];

    protected $casts = [
        'valor_vigente' => 'array',
        'valor_propuesto' => 'array',
        'resuelto_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCliente::class, 'importacion_cliente_id');
    }
}
