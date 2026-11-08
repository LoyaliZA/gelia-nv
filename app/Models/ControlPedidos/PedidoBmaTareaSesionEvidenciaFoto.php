<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaTareaSesionEvidenciaFoto extends Model
{
    protected $table = 'pedido_bma_tarea_sesion_evidencia_fotos';

    protected $fillable = [
        'pedido_bma_tarea_sesion_evidencia_id',
        'ruta',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function getNombreOriginalAttribute(?string $value): string
    {
        return \App\Support\ControlPedidos\NombreArchivoPedido::mostrar($value, 'evidencia', $this->pedido_bma_tarea_sesion_evidencia_id, $this->id);
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaSesionEvidencia::class, 'pedido_bma_tarea_sesion_evidencia_id');
    }
}
