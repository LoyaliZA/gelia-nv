<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PedidoBmaDocumento extends Model
{
    protected $table = 'pedido_bma_documentos';

    protected $fillable = [
        'pedido_bma_id',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'orden',
    ];

    protected $casts = [
        'tamano_bytes' => 'integer',
        'orden' => 'integer',
    ];

    protected $appends = ['url'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->ruta_archivo);
    }
}
