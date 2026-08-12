<?php

namespace App\Models\ControlPedidos;

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

    protected $table = 'pedido_bma_revisiones_producto';

    protected $fillable = [
        'pedido_bma_id',
        'orden',
        'descripcion_producto',
        'estado_fisico',
        'comentario',
        'unica_pieza',
        'mejor_ejemplar',
    ];

    protected $casts = [
        'unica_pieza' => 'boolean',
        'mejor_ejemplar' => 'boolean',
        'orden' => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
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
}
