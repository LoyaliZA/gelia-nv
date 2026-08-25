<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaTareaDocumento extends Model
{
    public const TIPO_EVIDENCIA_GENERAL = 'evidencia_general';

    public const TIPO_EVIDENCIA_PRODUCTO = 'evidencia_producto';

    public const TIPO_EVIDENCIA_INCIDENCIA = 'evidencia_incidencia';

    public const TIPO_IDENTIFICACION = 'identificacion';

    public const TIPO_REMISION = 'remision';

    protected $table = 'pedido_bma_tarea_documentos';

    protected $fillable = [
        'pedido_bma_tarea_preparacion_id',
        'pedido_bma_tarea_producto_id',
        'tipo_evidencia',
        'ruta_interna',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'hash_sha256',
        'subido_por_id',
        'subido_at',
        'inmutable',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'inmutable' => 'boolean',
            'version' => 'integer',
            'subido_at' => 'datetime',
        ];
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaPreparacion::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaProducto::class, 'pedido_bma_tarea_producto_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_id');
    }
}
