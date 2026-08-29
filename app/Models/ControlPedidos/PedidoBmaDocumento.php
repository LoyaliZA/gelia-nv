<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Builder;
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
    public const TIPO_EVIDENCIA_PESAJE = 'evidencia_pesaje';
    public const TIPO_EVIDENCIA_CONDICION = 'evidencia_condicion';

    public const RELACION_REVISION_GENERAL = 'revision_general';

    public const RELACION_REVISION_PRODUCTO = 'revision_producto';

    public const RELACION_ENVIO_CAJA = 'envio_caja';

    protected $table = 'pedido_bma_documentos';

    protected $fillable = [
        'pedido_bma_id',
        'pedido_bma_caja_id',
        'tipo',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'orden',
        'comentario',
        'relacion_tipo',
        'relacion_id',
        'reemplaza_documento_id',
        'activo',
        'sustituido_at',
        'sustituido_por_id',
    ];

    protected $casts = [
        'tamano_bytes' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
        'sustituido_at' => 'datetime',
    ];

    protected $appends = ['url'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaCaja::class, 'pedido_bma_caja_id');
    }

    public function reemplazaDocumento(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_documento_id');
    }

    public function sustituidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sustituido_por_id');
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeHistorico(Builder $query): Builder
    {
        return $query->where('activo', false);
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
