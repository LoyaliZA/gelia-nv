<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionCliente extends Model
{
    protected $table = 'importaciones_clientes';

    protected $fillable = [
        'usuario_id',
        'nombre_archivo_original',
        'ruta_almacenamiento',
        'filas_leidas',
        'filas_procesadas',
        'filas_omitidas',
        'errores',
        'ascensos',
        'clientes_marcados_inactivos',
        'duracion_seg',
    ];

    protected $casts = [
        'duracion_seg' => 'decimal:2',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function historialMontos(): HasMany
    {
        return $this->hasMany(HistorialMontoCliente::class, 'importacion_cliente_id');
    }
}
