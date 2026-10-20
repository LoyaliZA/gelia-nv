<?php

namespace App\Models\SaldosAFavor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PedidoBmaPagoArchivo extends Model
{
    protected $table = 'pedido_bma_pago_archivos';

    protected $fillable = [
        'pedido_bma_pago_id',
        'orden',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
    ];

    protected $casts = [
        'orden' => 'integer',
        'tamano_bytes' => 'integer',
    ];

    protected $appends = ['url'];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaPago::class, 'pedido_bma_pago_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->ruta_archivo
            ? Storage::disk('public')->url($this->ruta_archivo)
            : null;
    }
}
