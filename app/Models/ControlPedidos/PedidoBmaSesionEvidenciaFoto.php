<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PedidoBmaSesionEvidenciaFoto extends Model
{
    public const OBJETIVO_PRODUCTO = 'producto';

    public const OBJETIVO_CAJA = 'caja';

    protected $table = 'pedido_bma_sesion_evidencia_fotos';

    protected $fillable = [
        'sesion_id',
        'objetivo_tipo',
        'objetivo_uuid',
        'indice_caja',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'ip',
        'user_agent',
        'subido_en',
    ];

    protected $casts = [
        'indice_caja' => 'integer',
        'tamano_bytes' => 'integer',
        'subido_en' => 'datetime',
    ];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaSesionEvidencia::class, 'sesion_id');
    }

    public function urlPublicaDisco(): string
    {
        return Storage::disk('public')->url($this->ruta_archivo);
    }
}
