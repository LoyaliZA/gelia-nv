<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaPedidoBma extends Model
{
    public const ACCION_ELIMINACION = 'eliminacion';

    public const ACCION_RESTAURACION = 'restauracion';

    protected $table = 'auditorias_pedidos_bma';

    protected $fillable = [
        'pedido_bma_id',
        'usuario_id',
        'accion',
        'motivo',
        'fase_ciclo',
        'folio',
        'folio_remision',
        'estatus_id',
        'datos_snapshot',
    ];

    protected $casts = [
        'datos_snapshot' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id')->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
