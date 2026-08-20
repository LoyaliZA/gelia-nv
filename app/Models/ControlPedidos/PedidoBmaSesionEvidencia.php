<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use App\Support\FormPublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaSesionEvidencia extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_EXPIRADA = 'expirada';

    public const ESTADO_CERRADA = 'cerrada';

    public const TTL_MINUTOS = 10;

    public const MAX_FOTOS = 40;

    protected $table = 'pedido_bma_sesiones_evidencia';

    protected $fillable = [
        'pedido_bma_id',
        'token_hash',
        'codigo_publico',
        'estado',
        'expira_en',
        'creado_por',
        'reclamado_en',
        'claim_ip',
        'claim_ua',
        'cancelado_por',
        'cancelado_en',
        'snapshot_json',
    ];

    protected $casts = [
        'expira_en' => 'datetime',
        'reclamado_en' => 'datetime',
        'cancelado_en' => 'datetime',
        'snapshot_json' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function creadoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PedidoBmaSesionEvidenciaFoto::class, 'sesion_id');
    }

    public function estaAbierta(): bool
    {
        if (in_array($this->estado, [self::ESTADO_CANCELADA, self::ESTADO_EXPIRADA, self::ESTADO_CERRADA], true)) {
            return false;
        }

        if ($this->expira_en !== null && $this->expira_en->isPast()) {
            return false;
        }

        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_ACTIVA], true);
    }

    public function urlPublica(): ?string
    {
        if (! $this->codigo_publico) {
            return null;
        }

        return FormPublicUrl::cedisEvidenciaShow($this->codigo_publico);
    }
}
