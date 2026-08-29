<?php

namespace App\Models\SaldosAFavor;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class PedidoBmaPago extends Model
{
    public const REVISION_PENDIENTE = 'pendiente';

    public const REVISION_EN_REVISION = 'en_revision';

    public const REVISION_VERIFICADO = 'verificado';

    public const REVISION_CON_OBSERVACIONES = 'con_observaciones';

    public const REVISION_RECHAZADO = 'rechazado';

    /** @deprecated Use REVISION_VERIFICADO */
    public const REVISION_CONFIRMADO = self::REVISION_VERIFICADO;

    /** @deprecated Use REVISION_CON_OBSERVACIONES */
    public const REVISION_CON_DIFERENCIA = self::REVISION_CON_OBSERVACIONES;

    public const ESTADOS_REVISION = [
        self::REVISION_PENDIENTE,
        self::REVISION_EN_REVISION,
        self::REVISION_VERIFICADO,
        self::REVISION_CON_OBSERVACIONES,
        self::REVISION_RECHAZADO,
    ];

    /** @var list<string> */
    public const FORMAS_PAGO = [
        'transferencia',
        'deposito',
        'efectivo',
        'tarjeta',
        'otro',
    ];

    /**
     * Propiedad configurable por método (no condiciones rígidas por nombre en callers).
     *
     * @var array<string, bool>
     */
    public const REQUIERE_BANCO = [
        'transferencia' => true,
        'deposito' => true,
        'efectivo' => false,
        'tarjeta' => false,
        'otro' => false,
    ];

    /** @var array<string, string> */
    public const LABELS_FORMA_PAGO = [
        'transferencia' => 'Transferencia',
        'deposito' => 'Depósito',
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'otro' => 'Otro',
    ];

    protected $table = 'pedido_bma_pagos';

    protected $fillable = [
        'pedido_bma_id',
        'reemplaza_pago_id',
        'numero_exhibicion',
        'monto',
        'catalogo_banco_id',
        'forma_pago',
        'fecha_pago',
        'referencia',
        'ruta_archivo',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'capturado_por_id',
        'estado_revision',
        'revisado_por_id',
        'revisado_at',
        'observaciones',
        'activo_para_cobertura',
        'rechazado_at',
        'rechazado_por_id',
        'motivo_rechazo',
        'sustituido_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'numero_exhibicion' => 'integer',
        'tamano_bytes' => 'integer',
        'fecha_pago' => 'datetime',
        'revisado_at' => 'datetime',
        'activo_para_cobertura' => 'boolean',
        'rechazado_at' => 'datetime',
        'sustituido_at' => 'datetime',
    ];

    protected $appends = ['url'];

    public static function formaRequiereBanco(?string $formaPago): bool
    {
        if ($formaPago === null || $formaPago === '') {
            return false;
        }

        return (bool) (self::REQUIERE_BANCO[$formaPago] ?? false);
    }

    public static function labelForma(?string $formaPago): ?string
    {
        if ($formaPago === null || $formaPago === '') {
            return null;
        }

        return self::LABELS_FORMA_PAGO[$formaPago] ?? $formaPago;
    }

    /** @return list<array{codigo: string, label: string, requiere_banco: bool}> */
    public static function formasPagoCatalogo(): array
    {
        return array_map(
            fn (string $codigo) => [
                'codigo' => $codigo,
                'label' => self::LABELS_FORMA_PAGO[$codigo] ?? $codigo,
                'requiere_banco' => self::formaRequiereBanco($codigo),
            ],
            self::FORMAS_PAGO
        );
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function capturadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturado_por_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por_id');
    }

    /** Pago anterior que este registro sustituye. */
    public function reemplaza(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_pago_id');
    }

    /** Sustituto vigente (si existe). */
    public function sustituto(): HasOne
    {
        return $this->hasOne(self::class, 'reemplaza_pago_id');
    }

    public function cierreItems(): HasMany
    {
        return $this->hasMany(PedidoBmaCierrePagoItem::class, 'pedido_bma_pago_id');
    }

    public function urlEvidenciaReporte(): ?string
    {
        if (! $this->id || ! $this->ruta_archivo) {
            return null;
        }

        return route('reportes.pagos_pedidos.evidencia_pago', ['pago' => $this->id]);
    }

    public function scopeActivosParaCobertura(Builder $query): Builder
    {
        return $query->where('activo_para_cobertura', true);
    }

    public function esEditableBorrador(): bool
    {
        return $this->activo_para_cobertura
            && $this->estado_revision === self::REVISION_PENDIENTE
            && $this->rechazado_at === null
            && $this->sustituido_at === null;
    }

    public function getUrlAttribute(): ?string
    {
        return $this->ruta_archivo
            ? Storage::disk('public')->url($this->ruta_archivo)
            : null;
    }
}
