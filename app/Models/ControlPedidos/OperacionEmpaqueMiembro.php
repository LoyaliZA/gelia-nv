<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacionEmpaqueMiembro extends Model
{
    protected $table = 'operacion_empaque_miembros';

    protected $fillable = [
        'operacion_empaque_id',
        'pedido_bma_id',
        'es_principal',
        'cantidad_piezas',
        'orden',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'cantidad_piezas' => 'integer',
        'orden' => 'integer',
    ];

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(OperacionEmpaque::class, 'operacion_empaque_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }
}
