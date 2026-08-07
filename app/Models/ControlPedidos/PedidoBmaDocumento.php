<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PedidoBmaDocumento extends Model
{
    public const TIPO_COMPROBANTE = 'comprobante';
    public const TIPO_REMISION = 'remision';
    public const TIPO_GUIA = 'guia';
    public const TIPO_EVIDENCIA_APARTADO = 'evidencia_apartado';
    public const TIPO_PDF_PEDIDO = 'pdf_pedido';
    public const TIPO_ANEXO_PIEZAS = 'anexo_piezas';

    protected $table = 'pedido_bma_documentos';

    protected $fillable = [
        'pedido_bma_id',
        'tipo',
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

    /** URL autenticada (gate VisibilidadPedidoBma); fallback público solo si falta id. */
    public function getUrlAttribute(): string
    {
        if ($this->pedido_bma_id && $this->id) {
            return route('control_pedidos.documentos.show', [
                'pedidoBma' => $this->pedido_bma_id,
                'documento' => $this->id,
            ]);
        }

        return Storage::disk('public')->url($this->ruta_archivo);
    }
}
