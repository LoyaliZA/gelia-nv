<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use App\Support\FormPublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaTareaSesionEvidencia extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_EXPIRADA = 'expirada';

    public const TTL_MINUTOS = 20;

    protected $table = 'pedido_bma_tarea_sesiones_evidencia';

    protected $fillable = [
        'pedido_bma_tarea_preparacion_id',
        'token_hash',
        'codigo_publico',
        'estado',
        'expira_en',
        'creado_por',
        'reclamado_en',
        'claim_ip',
        'claim_ua',
        'snapshot_json',
        'tipos_evidencia_json',
    ];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'reclamado_en' => 'datetime',
            'snapshot_json' => 'array',
            'tipos_evidencia_json' => 'array',
        ];
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaPreparacion::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PedidoBmaTareaSesionEvidenciaFoto::class, 'pedido_bma_tarea_sesion_evidencia_id')
            ->orderBy('orden');
    }

    public function urlPublica(): string
    {
        return FormPublicUrl::tiendaEvidenciaShow($this->codigo_publico);
    }

    public function vigente(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_ACTIVA], true)
            && $this->expira_en?->isFuture();
    }
}
