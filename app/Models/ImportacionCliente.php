<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionCliente extends Model
{
    public const MODALIDAD_NORMAL = 'actualizacion_normal';

    public const MODALIDAD_CORTE = 'actualizacion_con_corte';

    protected $table = 'importaciones_clientes';

    protected $fillable = [
        'estado', 'resumen', 'mensaje_fatal',
        'usuario_id',
        'modalidad_carga',
        'fecha_corte',
        'nombre_archivo_original',
        'ruta_almacenamiento',
        'filas_leidas',
        'filas_procesadas',
        'filas_omitidas',
        'errores',
        'ascensos',
        'descensos',
        'clientes_marcados_inactivos',
        'duracion_seg',
        'cursor_fila',
        'contadores',
    ];

    protected $casts = [
        'resumen' => 'array',
        'contadores' => 'array',
        'duracion_seg' => 'decimal:2',
        'fecha_corte' => 'datetime',
    ];

    public function cursorConfirmado(): int
    {
        $resumen = is_array($this->resumen) ? (int) ($this->resumen['cursor_fila'] ?? 0) : 0;

        return max((int) $this->cursor_fila, $resumen);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function historialMontos(): HasMany
    {
        return $this->hasMany(HistorialMontoCliente::class, 'importacion_cliente_id');
    }

    public function erroresImportacion(): HasMany
    {
        return $this->hasMany(ErroresImportacionCliente::class, 'importacion_cliente_id');
    }

    public function cambiosLista(): HasMany
    {
        return $this->hasMany(CambioListaImportacionCliente::class, 'importacion_cliente_id');
    }
}
