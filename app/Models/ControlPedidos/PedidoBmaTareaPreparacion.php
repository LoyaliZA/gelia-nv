<?php

namespace App\Models\ControlPedidos;

use App\Models\Almacen;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoBmaTareaPreparacion extends Model
{
    use SoftDeletes;

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_EN_ATENCION = 'EN_ATENCION';

    public const ESTADO_RESPONDIDA = 'RESPONDIDA';

    public const ESTADO_LISTA_PARA_TRASLADO = 'LISTA_PARA_TRASLADO';

    public const ESTADO_LISTA_PARA_CARATULA = 'LISTA_PARA_CARATULA';

    public const ESTADO_EN_TRASLADO = 'EN_TRASLADO';

    public const ESTADO_RECIBIDA_CEDIS = 'RECIBIDA_CEDIS';

    public const ESTADO_RECHAZADA_CEDIS = 'RECHAZADA_CEDIS';

    public const ESTADO_CON_INCIDENCIA = 'CON_INCIDENCIA';

    public const ESTADO_LIBERACION_SOLICITADA = 'LIBERACION_SOLICITADA';

    public const ESTADO_LIBERADA = 'LIBERADA';

    public const ESTADO_CANCELADA = 'CANCELADA';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_ATENCION,
        self::ESTADO_RESPONDIDA,
        self::ESTADO_LISTA_PARA_TRASLADO,
        self::ESTADO_LISTA_PARA_CARATULA,
        self::ESTADO_EN_TRASLADO,
        self::ESTADO_RECIBIDA_CEDIS,
        self::ESTADO_RECHAZADA_CEDIS,
        self::ESTADO_CON_INCIDENCIA,
        self::ESTADO_LIBERACION_SOLICITADA,
        self::ESTADO_LIBERADA,
        self::ESTADO_CANCELADA,
    ];

    public const LABELS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_EN_ATENCION => 'En atención',
        self::ESTADO_RESPONDIDA => 'Respondida',
        self::ESTADO_LISTA_PARA_TRASLADO => 'Lista para traslado',
        self::ESTADO_LISTA_PARA_CARATULA => 'Lista para carátula',
        self::ESTADO_EN_TRASLADO => 'En traslado',
        self::ESTADO_RECIBIDA_CEDIS => 'Recibida en CEDIS',
        self::ESTADO_RECHAZADA_CEDIS => 'Rechazada por CEDIS',
        self::ESTADO_CON_INCIDENCIA => 'Con incidencia',
        self::ESTADO_LIBERACION_SOLICITADA => 'Liberación solicitada',
        self::ESTADO_LIBERADA => 'Liberada',
        self::ESTADO_CANCELADA => 'Cancelada',
    ];

    protected $table = 'pedido_bma_tareas_preparacion';

    protected $fillable = [
        'pedido_bma_id',
        'catalogo_modalidad_preparacion_id',
        'almacen_id',
        'area_responsable_codigo',
        'estado',
        'solicitada_por_id',
        'solicitada_at',
        'asignada_a_id',
        'atendida_por_id',
        'atendida_at',
        'enviada_cedis_por_id',
        'enviada_cedis_at',
        'recibida_cedis_por_id',
        'recibida_cedis_at',
        'motivo_rechazo_cedis',
        'intento_traslado',
        'solicitud_traspaso_id',
        'fecha_limite',
        'observaciones_solicitud',
        'observaciones_respuesta',
        'peso_real_kg',
        'peso_volumetrico_kg',
        'catalogo_tipo_caja_id',
        'observaciones_fisicas',
        'destinatario_nombre',
        'destinatario_telefono',
        'municipio_destino',
        'direccion_referencia',
        'catalogo_paqueteria_id',
        'modalidad_cobro',
        'destinatario_es_cliente',
        'requiere_traslado_cedis',
        'tarea_anterior_id',
        'idempotencia_clave',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'solicitada_at' => 'datetime',
            'atendida_at' => 'datetime',
            'enviada_cedis_at' => 'datetime',
            'recibida_cedis_at' => 'datetime',
            'fecha_limite' => 'datetime',
            'requiere_traslado_cedis' => 'boolean',
            'version' => 'integer',
            'intento_traslado' => 'integer',
            'peso_real_kg' => 'float',
            'peso_volumetrico_kg' => 'float',
            'destinatario_es_cliente' => 'boolean',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoBma::class, 'pedido_bma_id');
    }

    public function modalidad(): BelongsTo
    {
        return $this->belongsTo(CatalogoModalidadPreparacionPedido::class, 'catalogo_modalidad_preparacion_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function solicitadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por_id');
    }

    public function asignadaA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_a_id');
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por_id');
    }

    public function tareaAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'tarea_anterior_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(PedidoBmaTareaProducto::class, 'pedido_bma_tarea_preparacion_id')->orderBy('orden');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PedidoBmaTareaDocumento::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(PedidoBmaTareaHistorial::class, 'pedido_bma_tarea_preparacion_id')->latest('id');
    }

    public function sesionesEvidencia(): HasMany
    {
        return $this->hasMany(PedidoBmaTareaSesionEvidencia::class, 'pedido_bma_tarea_preparacion_id');
    }

    public function solicitudTraspaso(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SolicitudTraspaso::class, 'solicitud_traspaso_id');
    }

    public function paqueteria(): BelongsTo
    {
        return $this->belongsTo(CatalogoPaqueteriaPedido::class, 'catalogo_paqueteria_id');
    }

    public function caratulas(): HasMany
    {
        return $this->hasMany(PedidoBmaCaratula::class, 'pedido_bma_tarea_preparacion_id')->orderByDesc('version');
    }

    public function caratulaVigente(): ?PedidoBmaCaratula
    {
        return $this->caratulas()
            ->whereIn('estado', [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA])
            ->orderByDesc('version')
            ->first();
    }

    public function enviadaCedisPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviada_cedis_por_id');
    }

    public function recibidaCedisPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibida_cedis_por_id');
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_EN_ATENCION,
            self::ESTADO_CON_INCIDENCIA,
            self::ESTADO_LIBERACION_SOLICITADA,
            self::ESTADO_LISTA_PARA_TRASLADO,
            self::ESTADO_LISTA_PARA_CARATULA,
            self::ESTADO_EN_TRASLADO,
            self::ESTADO_RECHAZADA_CEDIS,
        ], true);
    }

    public function requiereTrasladoCedis(): bool
    {
        return (bool) $this->requiere_traslado_cedis;
    }

    public function piezasSolicitadas(): int
    {
        return (int) $this->productos()->sum('cantidad_solicitada');
    }
}
