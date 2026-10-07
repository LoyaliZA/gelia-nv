<?php

namespace App\Models\Escalonamiento;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalonamientoCierre extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_AUTORIZADO = 'autorizado';

    public const ESTADO_APLICADO = 'aplicado';

    public const ESTADO_INVALIDADO = 'invalidado';

    protected $table = 'escalonamiento_cierres';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'version',
        'estado',
        'reconstruccion',
        'hash_snapshot',
        'simulado_en',
        'simulado_por_user_id',
        'autorizado_en',
        'autorizado_por_user_id',
        'aplicado_en',
        'aplicado_por_user_id',
        'reporte_ruta',
        'reporte_generado_en',
        'aplicacion_externa_declarada',
        'aplicacion_externa_en',
        'aplicacion_externa_evidencia',
        'ultimo_cliente_aplicado_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'reconstruccion' => 'boolean',
        'simulado_en' => 'datetime',
        'autorizado_en' => 'datetime',
        'aplicado_en' => 'datetime',
        'reporte_generado_en' => 'datetime',
        'aplicacion_externa_declarada' => 'boolean',
        'aplicacion_externa_en' => 'datetime',
        'ultimo_cliente_aplicado_id' => 'integer',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoPeriodo::class, 'escalonamiento_periodo_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EscalonamientoCierreDetalle::class, 'escalonamiento_cierre_id');
    }

    public function cambiosLista(): HasMany
    {
        return $this->hasMany(EscalonamientoCambioLista::class, 'escalonamiento_cierre_id');
    }

    public function simuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'simulado_por_user_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por_user_id');
    }

    public function aplicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicado_por_user_id');
    }

    public function estaAplicado(): bool
    {
        return $this->estado === self::ESTADO_APLICADO;
    }

    public function puedeAutorizarse(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function puedeAplicarse(): bool
    {
        return $this->estado === self::ESTADO_AUTORIZADO;
    }
}
