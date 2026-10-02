<?php

namespace App\Models\ControlPedidos;

use App\Models\SolicitudTraspaso;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaTraspasoDiscrepancia extends Model
{
    public $timestamps = false;

    protected $table = 'pedido_bma_traspaso_discrepancias';

    protected $fillable = [
        'solicitud_traspaso_id',
        'solicitud_traspaso_producto_id',
        'pedido_bma_origen_id',
        'sku',
        'piezas_tienda',
        'piezas_cedis',
        'momento',
        'registrado_por_id',
        'registrado_at',
        'datos',
    ];

    protected function casts(): array
    {
        return [
            'piezas_tienda' => 'integer',
            'piezas_cedis' => 'integer',
            'registrado_at' => 'datetime',
            'datos' => 'array',
        ];
    }

    public function solicitudTraspaso(): BelongsTo
    {
        return $this->belongsTo(SolicitudTraspaso::class, 'solicitud_traspaso_id');
    }

    public function pedidoOrigen(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_origen_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
