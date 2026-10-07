<?php

namespace App\Models\Escalonamiento;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoImportacionFila extends Model
{
    protected $table = 'escalonamiento_importacion_filas';

    protected $fillable = [
        'escalonamiento_importacion_id',
        'numero_fila',
        'resultado',
        'motivo',
        'interpretacion',
    ];

    protected $casts = [
        'interpretacion' => 'array',
        'numero_fila' => 'integer',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoImportacion::class, 'escalonamiento_importacion_id');
    }
}
