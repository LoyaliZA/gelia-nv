<?php

namespace App\Models\ControlPedidos;

use App\Models\CatalogoBanco;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PedidoBmaAnexoEnvio extends Model
{
    public const ESTATUS_PENDIENTE = 'pendiente';
    public const ESTATUS_APROBADO = 'aprobado';
    public const ESTATUS_RECHAZADO = 'rechazado';

    protected $table = 'pedido_bma_anexos_envio';

    protected $fillable = [
        'pedido_bma_id',
        'monto',
        'catalogo_banco_id',
        'comentarios',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'estatus',
        'motivo_rechazo',
        'registrado_por_id',
        'validado_por_id',
        'validado_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'tamano_bytes' => 'integer',
        'validado_at' => 'datetime',
    ];

    protected $appends = ['url'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->ruta_archivo);
    }

    public function esPendiente(): bool
    {
        return $this->estatus === self::ESTATUS_PENDIENTE;
    }
}
