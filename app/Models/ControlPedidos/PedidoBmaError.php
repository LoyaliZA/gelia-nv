<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaError extends Model
{
    public const ESTATUS_ABIERTO = 'abierto';

    public const ESTATUS_CORREGIDO = 'corregido';

    protected $table = 'pedido_bma_errores';

    protected $fillable = [
        'pedido_bma_id',
        'campos',
        'descripcion',
        'reportado_por_id',
        'responsable_dueno',
        'responsable_user_id',
        'reportado_at',
        'corregido_por_id',
        'corregido_at',
        'correccion_realizada',
        'estatus',
    ];

    protected $casts = [
        'campos' => 'array',
        'reportado_at' => 'datetime',
        'corregido_at' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por_id');
    }

    public function responsableUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function corregidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregido_por_id');
    }

    public function estaAbierto(): bool
    {
        return $this->estatus === self::ESTATUS_ABIERTO;
    }
}
