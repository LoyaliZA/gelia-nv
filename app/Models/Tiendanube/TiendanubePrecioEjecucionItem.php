<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioEjecucionItem extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_CONFIRMADO = 'confirmado';

    public const ESTADO_CONFLICTO = 'conflicto';

    public const ESTADO_REINTENTO_PENDIENTE = 'reintento_pendiente';

    public const ESTADO_RESULTADO_INCIERTO = 'resultado_incierto';

    public const ESTADO_FALLIDO = 'fallido';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $table = 'tiendanube_precio_ejecucion_items';

    protected $fillable = [
        'ejecucion_id',
        'producto_id',
        'variante_id',
        'producto_nombre',
        'variante_sku',
        'variante_atributos',
        'imagen_url',
        'campos_objetivo',
        'valor_aprobado',
        'valor_anterior',
        'valor_remoto_previo',
        'valor_confirmado',
        'estado',
        'intentos',
        'lease_token',
        'lease_expires_at',
        'siguiente_intento_at',
        'error_codigo',
        'error_mensaje',
        'evidencia',
    ];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'variante_id' => 'integer',
            'variante_atributos' => 'array',
            'campos_objetivo' => 'array',
            'valor_aprobado' => 'array',
            'valor_anterior' => 'array',
            'valor_remoto_previo' => 'array',
            'valor_confirmado' => 'array',
            'intentos' => 'integer',
            'lease_expires_at' => 'datetime',
            'siguiente_intento_at' => 'datetime',
            'evidencia' => 'array',
        ];
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioEjecucion::class, 'ejecucion_id');
    }

    public function esRecuperable(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_REINTENTO_PENDIENTE,
            self::ESTADO_RESULTADO_INCIERTO,
            self::ESTADO_FALLIDO,
        ], true);
    }
}
