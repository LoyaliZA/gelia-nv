<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaCancelacionOperativa extends Model
{
    public const ESTADO_SOLICITADA = 'SOLICITADA';

    public const ESTADO_LIBERACION_PENDIENTE = 'LIBERACION_PENDIENTE';

    public const ESTADO_LIBERADA = 'LIBERADA';

    public const ESTADO_REVERTIDA = 'REVERTIDA';

    public const ESTADO_FINALIZADA = 'FINALIZADA';

    public const ESTADOS_ACTIVOS = [
        self::ESTADO_SOLICITADA,
        self::ESTADO_LIBERACION_PENDIENTE,
        self::ESTADO_LIBERADA,
    ];

    public const ESTADOS = [
        self::ESTADO_SOLICITADA,
        self::ESTADO_LIBERACION_PENDIENTE,
        self::ESTADO_LIBERADA,
        self::ESTADO_REVERTIDA,
        self::ESTADO_FINALIZADA,
    ];

    protected $table = 'pedido_bma_cancelaciones_operativas';

    protected $fillable = [
        'pedido_bma_id',
        'estado',
        'motivo',
        'comentario',
        'solicitada_por_id',
        'solicitada_at',
        'liberacion_solicitada_por_id',
        'liberacion_solicitada_at',
        'liberada_por_id',
        'liberada_at',
        'revertida_por_id',
        'revertida_at',
        'motivo_reactivacion',
        'finalizada_por_id',
        'finalizada_at',
        'folio_anterior',
        'folio_nuevo',
        'resolucion_financiera',
        'requiere_resolucion_financiera',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'solicitada_at' => 'datetime',
            'liberacion_solicitada_at' => 'datetime',
            'liberada_at' => 'datetime',
            'revertida_at' => 'datetime',
            'finalizada_at' => 'datetime',
            'requiere_resolucion_financiera' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(PedidoBmaCancelacionOperativaTarea::class, 'pedido_bma_cancelacion_operativa_id');
    }

    public function solicitadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por_id');
    }

    public function estaActiva(): bool
    {
        return in_array($this->estado, self::ESTADOS_ACTIVOS, true);
    }

    public function puedeReactivar(): bool
    {
        if (! $this->estaActiva()) {
            return false;
        }

        return ! $this->tareas()
            ->where('estado_liberacion', PedidoBmaCancelacionOperativaTarea::LIBERACION_LIBERADA)
            ->exists();
    }
}
