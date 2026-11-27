<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MultienvioNacional extends Model
{
    protected $table = 'multienvios_nacionales';

    public const ESTADO_PENDIENTE_DECISION = 'pendiente_decision';

    public const ESTADO_ESTANDAR_SIN_SEGURO = 'estandar_sin_seguro';

    public const ESTADO_CONFIGURANDO = 'configurando';

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_REGULARIZACION_PENDIENTE = 'regularizacion_pendiente';

    protected $fillable = [
        'uuid',
        'pedido_origen_id',
        'revision_consulta',
        'estado',
        'decidido_por_id',
        'decidido_at',
        'activado_por_id',
        'activado_at',
        'snapshot_origen_json',
        'borrador_json',
        'regularizacion_json',
    ];

    protected $casts = [
        'decidido_at' => 'datetime',
        'activado_at' => 'datetime',
        'snapshot_origen_json' => 'array',
        'borrador_json' => 'array',
        'regularizacion_json' => 'array',
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_origen_id');
    }

    public function envios(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'multienvio_nacional_id')->orderBy('multienvio_orden');
    }
}
