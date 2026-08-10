<?php

namespace App\Models\SaldosAFavor;

use App\Models\CatalogoBanco;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'numero_exhibicion' => 'integer',
        'tamano_bytes' => 'integer',
        'fecha_pago' => 'datetime',
        'revisado_at' => 'datetime',
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

    public function getUrlAttribute(): ?string
    {
        return $this->ruta_archivo
            ? Storage::disk('public')->url($this->ruta_archivo)
            : null;
    }
}
