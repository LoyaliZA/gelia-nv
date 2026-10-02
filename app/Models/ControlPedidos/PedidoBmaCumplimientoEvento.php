<?php

namespace App\Models\ControlPedidos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoBmaCumplimientoEvento extends Model
{
    public const TIPO_PIEZA_SEPARADA = 'PIEZA_SEPARADA';

    public const TIPO_PAGO_CONFIRMADO = 'PAGO_CONFIRMADO';

    public const TIPO_SALIDA_AUTORIZADA = 'SALIDA_AUTORIZADA';

    public const TIPO_ENTREGADA = 'ENTREGADA';

    public const TIPO_EMPACADA = 'EMPACADA';

    public const TIPO_DESPACHADA = 'DESPACHADA';

    public const TIPO_PLAZO_EXTENDIDO = 'PLAZO_EXTENDIDO';

    public const TIPO_DEVOLUCION_SOLICITADA = 'DEVOLUCION_SOLICITADA';

    public const TIPO_DEVUELTA_ANAQUEL = 'DEVUELTA_ANAQUEL';

    public const TIPO_CAMBIO_MODALIDAD = 'CAMBIO_MODALIDAD';

    public const TIPO_CANCELACION = 'CANCELACION';

    public const TIPO_TRASPASO_SALIDA = 'TRASPASO_SALIDA';

    public const TIPO_TRASPASO_RECIBIDO_CEDIS = 'TRASPASO_RECIBIDO_CEDIS';

    public const TIPO_TRASPASO_RECHAZADO_CEDIS = 'TRASPASO_RECHAZADO_CEDIS';

    public const TIPOS = [
        self::TIPO_PIEZA_SEPARADA,
        self::TIPO_PAGO_CONFIRMADO,
        self::TIPO_SALIDA_AUTORIZADA,
        self::TIPO_ENTREGADA,
        self::TIPO_EMPACADA,
        self::TIPO_DESPACHADA,
        self::TIPO_PLAZO_EXTENDIDO,
        self::TIPO_DEVOLUCION_SOLICITADA,
        self::TIPO_DEVUELTA_ANAQUEL,
        self::TIPO_CAMBIO_MODALIDAD,
        self::TIPO_CANCELACION,
        self::TIPO_TRASPASO_SALIDA,
        self::TIPO_TRASPASO_RECIBIDO_CEDIS,
        self::TIPO_TRASPASO_RECHAZADO_CEDIS,
    ];

    public const LABELS = [
        self::TIPO_PIEZA_SEPARADA => 'Pieza separada',
        self::TIPO_PAGO_CONFIRMADO => 'Pago confirmado',
        self::TIPO_SALIDA_AUTORIZADA => 'Salida autorizada',
        self::TIPO_ENTREGADA => 'Entregada',
        self::TIPO_EMPACADA => 'Empacada',
        self::TIPO_DESPACHADA => 'Despachada',
        self::TIPO_PLAZO_EXTENDIDO => 'Plazo extendido',
        self::TIPO_DEVOLUCION_SOLICITADA => 'Devolución solicitada',
        self::TIPO_DEVUELTA_ANAQUEL => 'Devuelta al anaquel',
        self::TIPO_CAMBIO_MODALIDAD => 'Cambio de modalidad',
        self::TIPO_CANCELACION => 'Cancelación',
        self::TIPO_TRASPASO_SALIDA => 'Salida hacia CEDIS',
        self::TIPO_TRASPASO_RECIBIDO_CEDIS => 'Recibido en CEDIS',
        self::TIPO_TRASPASO_RECHAZADO_CEDIS => 'Rechazado en CEDIS',
    ];

    public $timestamps = false;

    protected $table = 'pedido_bma_cumplimiento_eventos';

    protected $fillable = [
        'pedido_bma_cumplimiento_fisico_id',
        'pedido_bma_tarea_preparacion_id',
        'tipo',
        'usuario_id',
        'motivo',
        'idempotencia_clave',
        'datos',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Los eventos de apartado físico no se modifican.');
        });
    }

    public function cumplimiento(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaCumplimientoFisico::class, 'pedido_bma_cumplimiento_fisico_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
