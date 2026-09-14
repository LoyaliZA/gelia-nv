<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePrecioEjecucion extends Model
{
    use HasUuids;

    public const CANAL_API = 'api';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_COMPLETADA = 'completada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_SUSPENDIDA = 'suspendida';

    public const ESTADO_FALLIDA = 'fallida';

    protected $table = 'tiendanube_precio_ejecuciones';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'lote_id',
        'revision_id',
        'store_id',
        'user_id',
        'config_generation',
        'api_version',
        'checksum_revision',
        'canal',
        'estado',
        'total',
        'pendientes',
        'procesando',
        'confirmadas',
        'conflictos',
        'por_verificar',
        'fallidas',
        'canceladas',
        'resumen_campos',
        'lease_token',
        'lease_expires_at',
        'error_codigo',
        'error_mensaje',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_id' => 'integer',
            'store_id' => 'integer',
            'user_id' => 'integer',
            'config_generation' => 'integer',
            'total' => 'integer',
            'pendientes' => 'integer',
            'procesando' => 'integer',
            'confirmadas' => 'integer',
            'conflictos' => 'integer',
            'por_verificar' => 'integer',
            'fallidas' => 'integer',
            'canceladas' => 'integer',
            'resumen_campos' => 'array',
            'lease_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLote::class, 'lote_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLoteRevision::class, 'revision_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TiendanubePrecioEjecucionItem::class, 'ejecucion_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(TiendanubePrecioEjecucionEvento::class, 'ejecucion_id');
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_PROCESANDO,
            self::ESTADO_SUSPENDIDA,
        ], true);
    }

    /**
     * @return array<string, int>
     */
    public function contadores(): array
    {
        $total = max(1, (int) $this->total);
        $resueltos = (int) $this->confirmadas + (int) $this->conflictos + (int) $this->fallidas + (int) $this->canceladas;
        $porcentaje = (int) floor(($resueltos / $total) * 100);
        if ((int) $this->total === 0) {
            $porcentaje = 0;
        }

        return [
            'total' => (int) $this->total,
            'pendientes' => (int) $this->pendientes,
            'procesando' => (int) $this->procesando,
            'confirmadas' => (int) $this->confirmadas,
            'conflictos' => (int) $this->conflictos,
            'por_verificar' => (int) $this->por_verificar,
            'fallidas' => (int) $this->fallidas,
            'canceladas' => (int) $this->canceladas,
            'porcentaje' => min(100, $porcentaje),
        ];
    }
}
