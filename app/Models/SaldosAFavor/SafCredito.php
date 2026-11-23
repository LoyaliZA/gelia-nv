<?php

namespace App\Models\SaldosAFavor;

use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafCredito extends Model
{
    /** Uso permitido (S-06): general, solo transporte, o pendiente de que Administración confirme su origen. */
    public const USO_GENERAL = 'general';

    public const USO_SOLO_TRANSPORTE = 'solo_transporte';

    public const USO_PENDIENTE_CONCILIACION = 'pendiente_conciliacion';

    public const USOS = [self::USO_GENERAL, self::USO_SOLO_TRANSPORTE, self::USO_PENDIENTE_CONCILIACION];

    public const ORIGEN_TRANSPORTE_CONFIRMADO = 'transporte_confirmado';

    public const ORIGEN_MERCANCIA = 'mercancia';

    public const ORIGEN_MIXTO = 'mixto';

    public const ORIGEN_NO_DETERMINADO = 'no_determinado';

    public const ORIGENES = [self::ORIGEN_TRANSPORTE_CONFIRMADO, self::ORIGEN_MERCANCIA, self::ORIGEN_MIXTO, self::ORIGEN_NO_DETERMINADO];

    public const ESTADO_DISPONIBLE = 'disponible';

    /** @deprecated Prefer ESTADO_DISPONIBLE; se remapea en recalcularEstadoFinanciero */
    public const ESTADO_PARCIAL = 'parcialmente_aplicado';

    public const ESTADO_RESERVADO = 'reservado';

    public const ESTADO_APLICADO = 'aplicado';

    public const ESTADO_VENCIDO = 'vencido';

    public const ESTADO_CANCELADO = 'cancelado';

    public const REVISION_PENDIENTE = 'pendiente';

    public const REVISION_REVISADO = 'revisado';

    public const REVISION_CON_DIFERENCIA = 'con_diferencia';

    public const REVISION_REQUIERE_CORRECCION = 'requiere_correccion';

    public const REVISION_AJUSTADO = 'ajustado';

    public const REVISION_RECHAZADO = 'rechazado';

    /** @deprecated Usar ReglasSaf::vigenciaDias(); se mantiene como fallback de tests legacy */
    public const VIGENCIA_DIAS = 20;

    /** Estados con saldo usable (disponible para reservar/aplicar). */
    public const ESTADOS_USABLES = [
        self::ESTADO_DISPONIBLE,
        self::ESTADO_PARCIAL,
    ];

    protected $table = 'saf_creditos';

    protected $fillable = [
        'folio',
        'saf_cuenta_id',
        'cliente_id',
        'canal_origen',
        'sucursal',
        'departamento',
        'pedido_bma_id',
        'documento_origen',
        'monto_original',
        'monto_aplicado',
        'monto_reservado',
        'monto_disponible',
        'fecha_generacion',
        'fecha_vencimiento',
        'saf_motivo_id',
        'detalle_motivo',
        'generado_por_id',
        'estado_financiero',
        'estado_revision',
        'uso_permitido',
        'origen_economico',
        'clasificacion_fuente',
        'clasificacion_motivo',
        'clasificado_por_id',
        'clasificado_at',
        'observaciones_revision',
        'revisado_por_id',
        'revisado_at',
    ];

    protected $casts = [
        'monto_original' => 'decimal:2',
        'monto_aplicado' => 'decimal:2',
        'monto_reservado' => 'decimal:2',
        'monto_disponible' => 'decimal:2',
        'fecha_generacion' => 'datetime',
        'fecha_vencimiento' => 'date',
        'revisado_at' => 'datetime',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(SafCuenta::class, 'saf_cuenta_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(SafMotivo::class, 'saf_motivo_id');
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function pedidoOrigen(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(SafMovimiento::class, 'saf_credito_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(SafEvidencia::class, 'saf_credito_id');
    }

    public function aplicacionesPedido(): HasMany
    {
        return $this->hasMany(SafPedidoAplicacion::class, 'saf_credito_id');
    }

    protected static function booted(): void
    {
        // Ninguna escritura guarda una clasificación inventada.
        static::saving(function (SafCredito $credito) {
            if ($credito->uso_permitido !== null && ! in_array($credito->uso_permitido, self::USOS, true)) {
                throw new \InvalidArgumentException('El uso permitido del saldo a favor no es válido.');
            }
            if ($credito->origen_economico !== null && ! in_array($credito->origen_economico, self::ORIGENES, true)) {
                throw new \InvalidArgumentException('El origen económico del saldo a favor no es válido.');
            }
        });
    }

    public function puedeUsarse(): bool
    {
        return in_array($this->estado_financiero, self::ESTADOS_USABLES, true)
            && (float) $this->monto_disponible > 0;
    }

    public function recalcularEstadoFinanciero(): void
    {
        if ($this->estado_financiero === self::ESTADO_CANCELADO) {
            return;
        }
        if ($this->estado_financiero === self::ESTADO_VENCIDO && (float) $this->monto_disponible <= 0
            && (float) $this->monto_reservado <= 0) {
            $this->estado_financiero = self::ESTADO_APLICADO;

            return;
        }
        if ($this->estado_financiero === self::ESTADO_VENCIDO) {
            return;
        }

        $disponible = (float) $this->monto_disponible;
        $reservado = (float) $this->monto_reservado;
        $aplicado = (float) $this->monto_aplicado;

        if ($disponible <= 0 && $reservado > 0) {
            $this->estado_financiero = self::ESTADO_RESERVADO;
        } elseif ($disponible <= 0 && $aplicado > 0) {
            $this->estado_financiero = self::ESTADO_APLICADO;
        } else {
            // Disponible (incluye remanente tras aplicación parcial)
            $this->estado_financiero = self::ESTADO_DISPONIBLE;
        }
    }

    public function sincronizarDisponible(): void
    {
        $this->monto_disponible = round(
            (float) $this->monto_original - (float) $this->monto_aplicado - (float) $this->monto_reservado,
            2
        );
        $this->recalcularEstadoFinanciero();
    }
}
