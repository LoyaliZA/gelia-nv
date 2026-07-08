<?php

namespace App\Models\Almacenes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionAlmacenError extends Model
{
    protected $table = 'importaciones_almacen_errores';

    protected $fillable = [
        'importacion_almacen_log_id',
        'fila',
        'referencia',
        'campo',
        'mensaje',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(ImportacionAlmacenLog::class, 'importacion_almacen_log_id');
    }
}
