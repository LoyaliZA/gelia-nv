<?php

namespace App\Models\ControlPedidos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoBmaCumplimientoFisico extends Model
{
    public const ESTADO_POR_SEPARAR = 'POR_SEPARAR';

    public const ESTADO_SEPARADA = 'SEPARADA';

    public const ESTADO_LISTA_PARA_SALIDA = 'LISTA_PARA_SALIDA';

    public const ESTADO_EMPACADA = 'EMPACADA';

    public const ESTADO_DESPACHADA = 'DESPACHADA';

    public const ESTADO_ENTREGADA = 'ENTREGADA';

    public const ESTADO_DEVOLUCION_PENDIENTE = 'DEVOLUCION_PENDIENTE';

    public const ESTADO_DEVUELTA_ANAQUEL = 'DEVUELTA_ANAQUEL';

    public const ESTADO_INCIDENCIA = 'INCIDENCIA';

    public const ESTADOS = [
        self::ESTADO_POR_SEPARAR,
        self::ESTADO_SEPARADA,
        self::ESTADO_LISTA_PARA_SALIDA,
        self::ESTADO_EMPACADA,
        self::ESTADO_DESPACHADA,
        self::ESTADO_ENTREGADA,
        self::ESTADO_DEVOLUCION_PENDIENTE,
        self::ESTADO_DEVUELTA_ANAQUEL,
        self::ESTADO_INCIDENCIA,
    ];

    public const ESTADOS_CON_MERCANCIA = [
        self::ESTADO_SEPARADA,
        self::ESTADO_LISTA_PARA_SALIDA,
        self::ESTADO_EMPACADA,
    ];

    public const LABELS = [
        self::ESTADO_POR_SEPARAR => 'Por separar',
        self::ESTADO_SEPARADA => 'Separada',
        self::ESTADO_LISTA_PARA_SALIDA => 'Lista para salida',
        self::ESTADO_EMPACADA => 'Empacada',
        self::ESTADO_DESPACHADA => 'Despachada',
        self::ESTADO_ENTREGADA => 'Entregada',
        self::ESTADO_DEVOLUCION_PENDIENTE => 'Devolución pendiente',
        self::ESTADO_DEVUELTA_ANAQUEL => 'Devuelta al anaquel',
        self::ESTADO_INCIDENCIA => 'Incidencia',
    ];

    protected $table = 'pedido_bma_cumplimiento_fisico';

    protected $fillable = [
        'pedido_bma_tarea_preparacion_id',
        'estado',
        'cantidad',
        'ubicacion',
        'vence_at',
        'version',
        'condicion_cobro',
        'pago_confirmado_at',
        'pago_confirmado_por_id',
        'pedido_bma_pago_id',
        'folio_operacion',
        'salida_autorizada_at',
        'salida_autorizada_por_id',
        'entregada_at',
        'entregada_por_id',
        'receptor_nombre',
        'bultos_salida',
        'empacado_at',
        'empacado_por_id',
        'despachada_at',
        'despachada_por_id',
        'resguardo_pdv_id',
        'entrega_pdv_id',
        'prorroga_aplicada',
        'prorroga_por_id',
        'prorroga_motivo',
        'prorroga_at',
        'devolucion_solicitada_at',
        'devuelta_at',
        'devuelta_por_id',
        'documento_devolucion_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'vence_at' => 'datetime',
            'version' => 'integer',
            'pago_confirmado_at' => 'datetime',
            'salida_autorizada_at' => 'datetime',
            'entregada_at' => 'datetime',
            'bultos_salida' => 'integer',
            'empacado_at' => 'datetime',
            'despachada_at' => 'datetime',
            'prorroga_aplicada' => 'boolean',
            'prorroga_at' => 'datetime',
            'devolucion_solicitada_at' => 'datetime',
            'devuelta_at' => 'datetime',
        ];
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaPreparacion::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(PedidoBmaCumplimientoEvento::class, 'pedido_bma_cumplimiento_fisico_id')->orderBy('id');
    }

    public function prorrogaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prorroga_por_id');
    }

    public function devueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devuelta_por_id');
    }

    public function pagoConfirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_confirmado_por_id');
    }

    public function salidaAutorizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salida_autorizada_por_id');
    }

    public function entregadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregada_por_id');
    }

    public function empacadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empacado_por_id');
    }

    public function despachadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despachada_por_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaPago::class, 'pedido_bma_pago_id');
    }

    public function resguardoPdv(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdv::class, 'resguardo_pdv_id');
    }

    public function entregaPdv(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvEntrega::class, 'entrega_pdv_id');
    }

    public function documentoDevolucion(): BelongsTo
    {
        return $this->belongsTo(PedidoBmaTareaDocumento::class, 'documento_devolucion_id');
    }

    public function tieneMercanciaApartada(): bool
    {
        return in_array($this->estado, self::ESTADOS_CON_MERCANCIA, true) && (int) $this->cantidad > 0;
    }
}
