<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaHistorialEstado extends Model
{
    protected $table = 'pedido_bma_historial_estados';

    protected $fillable = [
        'pedido_bma_id',
        'usuario_id',
        'accion',
        'rol',
        'departamento',
        'estatus_anterior_id',
        'estatus_nuevo_id',
        'comentarios',
        'evidencia_ruta',
        'evidencia_nombre',
    ];

    protected $appends = [
        'accion_etiqueta',
    ];

    public function getAccionEtiquetaAttribute(): ?string
    {
        return AccionesHistorialPedidoBma::etiqueta($this->accion);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function estatusAnterior(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstatusPedido::class, 'estatus_anterior_id');
    }

    public function estatusNuevo(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstatusPedido::class, 'estatus_nuevo_id');
    }
}
