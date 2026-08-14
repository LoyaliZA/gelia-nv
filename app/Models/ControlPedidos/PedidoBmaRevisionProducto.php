<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaRevisionProducto extends Model
{
    public const ESTADO_BUENO = 'bueno';

    public const ESTADO_REGULAR = 'regular';

    public const ESTADO_MALO = 'malo';

    public const ESTADO_DANADO = 'danado';

    public const ESTADO_SIN_EXISTENCIA = 'sin_existencia';

    public const ESTADOS = [
        self::ESTADO_BUENO,
        self::ESTADO_REGULAR,
        self::ESTADO_MALO,
        self::ESTADO_DANADO,
        self::ESTADO_SIN_EXISTENCIA,
    ];

    public const LABELS = [
        self::ESTADO_BUENO => 'Bueno',
        self::ESTADO_REGULAR => 'Regular',
        self::ESTADO_MALO => 'Malo',
        self::ESTADO_DANADO => 'Dañado',
        self::ESTADO_SIN_EXISTENCIA => 'Sin existencias',
    ];

    public const RESOLUCION_CONTACTAR = 'contactar';

    public const RESOLUCION_ESPERAR = 'esperar';

    public const RESOLUCION_RETIRAR = 'retirar';

    public const RESOLUCION_SUSTITUIR = 'sustituir';

    public const RESOLUCION_STOCK_OK = 'stock_ok';

    public const RESOLUCIONES = [
        self::RESOLUCION_CONTACTAR,
        self::RESOLUCION_ESPERAR,
        self::RESOLUCION_RETIRAR,
        self::RESOLUCION_SUSTITUIR,
        self::RESOLUCION_STOCK_OK,
    ];

    public const RESOLUCIONES_CIERRAN = [
        self::RESOLUCION_RETIRAR,
        self::RESOLUCION_SUSTITUIR,
        self::RESOLUCION_STOCK_OK,
    ];

    public const LABELS_RESOLUCION = [
        self::RESOLUCION_CONTACTAR => 'Contactar cliente',
        self::RESOLUCION_ESPERAR => 'Esperar producto',
        self::RESOLUCION_RETIRAR => 'Retirar producto',
        self::RESOLUCION_SUSTITUIR => 'Sustituir producto',
        self::RESOLUCION_STOCK_OK => 'Ya hay existencias',
    ];

    protected $table = 'pedido_bma_revisiones_producto';

    protected $fillable = [
        'pedido_bma_id',
        'orden',
        'descripcion_producto',
        'producto_id',
        'sku',
        'estado_fisico',
        'comentario',
        'unica_pieza',
        'mejor_ejemplar',
        'resolucion',
        'resolucion_nota',
        'resolucion_por_id',
        'resolucion_at',
    ];

    protected $casts = [
        'unica_pieza' => 'boolean',
        'mejor_ejemplar' => 'boolean',
        'orden' => 'integer',
        'producto_id' => 'integer',
        'resolucion_at' => 'datetime',
    ];

    protected $appends = [
        'esta_abierta',
        'resolucion_etiqueta',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function resolucionPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolucion_por_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'relacion_id')
            ->where('relacion_tipo', 'revision_producto')
            ->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION);
    }

    public static function requiereEvidencia(string $estado): bool
    {
        return in_array($estado, [self::ESTADO_MALO, self::ESTADO_DANADO], true);
    }

    /** Requiere comentario (malo/dañado/sin existencias). */
    public static function requiereComentario(string $estado): bool
    {
        return self::requiereEvidencia($estado)
            || $estado === self::ESTADO_SIN_EXISTENCIA;
    }

    /** Marca el pedido con observaciones para Ventas. */
    public static function esObservacionParaVentas(string $estado): bool
    {
        return self::requiereComentario($estado);
    }

    public function estaSinExistenciaAbierta(): bool
    {
        if ($this->estado_fisico !== self::ESTADO_SIN_EXISTENCIA) {
            return false;
        }

        return ! in_array($this->resolucion, self::RESOLUCIONES_CIERRAN, true);
    }

    public function getEstaAbiertaAttribute(): bool
    {
        return $this->estaSinExistenciaAbierta();
    }

    public function getResolucionEtiquetaAttribute(): ?string
    {
        if ($this->resolucion === null || $this->resolucion === '') {
            return null;
        }

        return self::LABELS_RESOLUCION[$this->resolucion] ?? $this->resolucion;
    }

    public function scopeSinExistenciaAbierta(Builder $query): Builder
    {
        return $query->where('estado_fisico', self::ESTADO_SIN_EXISTENCIA)
            ->where(function (Builder $q) {
                $q->whereNull('resolucion')
                    ->orWhereNotIn('resolucion', self::RESOLUCIONES_CIERRAN);
            });
    }
}
