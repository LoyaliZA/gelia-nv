<?php

namespace App\Models\SaldosAFavor;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafIncidencia extends Model
{
    public const ESTADO_ABIERTA = 'abierta';
    public const ESTADO_RESUELTA = 'resuelta';

    protected $table = 'saf_incidencias';

    protected $fillable = [
        'cliente_id',
        'saf_credito_id',
        'pedido_bma_id',
        'tipo',
        'descripcion',
        'estado',
        'creado_por_id',
        'resuelto_por_id',
        'resuelto_at',
    ];

    protected $casts = [
        'resuelto_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(SafCredito::class, 'saf_credito_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ControlPedidos\PedidoBma::class, 'pedido_bma_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }
}
